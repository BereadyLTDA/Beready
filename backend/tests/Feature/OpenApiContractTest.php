<?php

namespace Tests\Feature;

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use Illuminate\Routing\Route;
use Tests\TestCase;

/**
 * Garante que as rotas (routes/api.php) e o contrato OpenAPI (openapi.yaml) não divergem: toda rota
 * registrada está documentada (ou em uma das listas abaixo, com o motivo) e toda operação documentada
 * tem uma rota. O formato das respostas é conferido pelo ApiTestCase a cada requisição.
 *
 * As chaves são sempre "MÉTODO /template", com o template como no OpenAPI (/flashcards/{id}).
 */
class OpenApiContractTest extends TestCase
{
    public const ARQUIVO = __DIR__.'/../../openapi.yaml';

    /**
     * Rotas gêmeas: mesma action de uma operação documentada, registrada com outro path ou verbo.
     * O contrato documenta a forma que o frontend chama (ou a forma REST, se ele não chama nenhuma);
     * a outra fica aqui, apontando para a operação documentada, cujo schema também vale para ela.
     *
     * rota gêmea => operação documentada
     */
    public const ALIASES = [
        // Flashcards: o frontend lê por /flashcards/{id}, mas edita e exclui pelas formas legadas
        'GET /flashcards/view/{id}' => 'GET /flashcards/{id}',
        'PUT /flashcards/{id}' => 'PUT /flashcards/edit/{id}',
        'DELETE /flashcards/{id}' => 'DELETE /flashcards/delete/{id}',

        // Quizes: o frontend usa só a forma REST
        'GET /quizes/view/{id}' => 'GET /quizes/{id}',
        'PUT /quizes/edit/{id}' => 'PUT /quizes/{id}',
        'DELETE /quizes/delete/{id}' => 'DELETE /quizes/{id}',

        // Tags, prompts, traduções, imagens e frases: o frontend usa só a forma legada (view|edit|delete)
        'GET /tags/{id}' => 'GET /tags/view/{id}',
        'PUT /tags/{id}' => 'PUT /tags/edit/{id}',
        'DELETE /tags/{id}' => 'DELETE /tags/delete/{id}',

        'GET /prompts/{id}' => 'GET /prompts/view/{id}',
        'PUT /prompts/{id}' => 'PUT /prompts/edit/{id}',
        'DELETE /prompts/{id}' => 'DELETE /prompts/delete/{id}',

        'GET /traducoes/{id}' => 'GET /traducoes/view/{id}',
        'PUT /traducoes/{id}' => 'PUT /traducoes/edit/{id}',
        'DELETE /traducoes/{id}' => 'DELETE /traducoes/delete/{id}',

        'GET /imagens/{id}' => 'GET /imagens/view/{id}',
        'PUT /imagens/{id}' => 'PUT /imagens/edit/{id}',
        'DELETE /imagens/{id}' => 'DELETE /imagens/delete/{id}',

        'GET /frases/{id}' => 'GET /frases/view/{id}',
        'PUT /frases/{id}' => 'PUT /frases/edit/{id}',
        'DELETE /frases/{id}' => 'DELETE /frases/delete/{id}',

        // Usuários: a forma REST (/users/{id}) não pode ser a documentada porque, para o OpenAPI,
        // tem o mesmo template de /users/{uuid} (outra action, com outra resposta)
        'GET /users/{id}' => 'GET /users/view/{id}',
        // O frontend atualiza o perfil com PUT; a mesma rota também aceita POST
        'POST /users/update/{id}' => 'PUT /users/update/{id}',

        // Planos (admin): o frontend usa PUT e DELETE; as mesmas rotas também aceitam POST
        'POST /admin/planos/edit/{id}' => 'PUT /admin/planos/edit/{id}',
        'POST /admin/planos/delete/{id}' => 'DELETE /admin/planos/delete/{id}',
    ];

    /**
     * Rotas com action própria que o OpenAPI não consegue descrever em separado: o template é
     * idêntico ao de uma operação documentada (só muda o padrão do parâmetro).
     *
     * rota => operação documentada que a descreve
     */
    public const MESMO_TEMPLATE = [
        // viewByUuid devolve o mesmo flashcard de view; em GET /flashcards/{id} o parâmetro aceita
        // o id numérico ou o UUID
        'GET /flashcards/{uuid}' => 'GET /flashcards/{id}',
    ];

    private static ?OpenApi $contrato = null;

    /**
     * Template da rota do Laravel no formato do contrato ("flashcards/{id}" => "/flashcards/{id}").
     */
    public static function template(string $uri): string
    {
        return '/'.ltrim($uri, '/');
    }

    /**
     * Operação do contrato cujo schema vale para a rota: ela mesma, ou a que as listas indicam.
     */
    public static function operacaoEquivalente(string $rota): string
    {
        return self::ALIASES[$rota] ?? self::MESMO_TEMPLATE[$rota] ?? $rota;
    }

    /**
     * @return list<string> "MÉTODO /template" de cada operação do contrato
     */
    public static function operacoesDoContrato(OpenApi $contrato): array
    {
        static $cache = [];
        $chave = spl_object_id($contrato);
        if (isset($cache[$chave])) {
            return $cache[$chave];
        }

        $operacoes = [];
        foreach ($contrato->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $metodo) {
                $operacoes[] = strtoupper((string) $metodo).' '.$path;
            }
        }

        return $cache[$chave] = $operacoes;
    }

    public function test_contrato_eh_um_openapi_valido(): void
    {
        $contrato = $this->contrato();

        $this->assertSame('3.0.3', $contrato->openapi);
        $this->assertTrue($contrato->validate(), "openapi.yaml inválido:\n- ".implode("\n- ", $contrato->getErrors()));
    }

    public function test_toda_rota_esta_no_contrato_ou_em_uma_das_listas(): void
    {
        $conhecidas = $this->operacoesDocumentadas()
            + array_fill_keys(array_keys(self::ALIASES), true)
            + array_fill_keys(array_keys(self::MESMO_TEMPLATE), true);

        $faltando = array_keys(array_diff_key($this->rotasRegistradas(), $conhecidas));
        sort($faltando);

        $this->assertSame(
            [],
            $faltando,
            "Rotas de routes/api.php sem operação em openapi.yaml.\n"
            .'Documente-as no contrato ou, se forem gêmeas de uma operação já documentada, inclua-as em '
            .self::class.'::ALIASES.',
        );
    }

    public function test_toda_operacao_do_contrato_tem_rota(): void
    {
        $semRota = array_keys(array_diff_key($this->operacoesDocumentadas(), $this->rotasRegistradas()));
        sort($semRota);

        $this->assertSame([], $semRota, 'Operações de openapi.yaml sem rota em routes/api.php (método + template idênticos).');
    }

    /**
     * As listas não podem acumular entradas mortas nem esconder uma rota que não é gêmea de verdade.
     */
    public function test_listas_de_excecoes_estao_coerentes(): void
    {
        $rotas = $this->rotasRegistradas();
        $documentadas = $this->operacoesDocumentadas();

        foreach (self::ALIASES + self::MESMO_TEMPLATE as $rota => $operacao) {
            $this->assertArrayHasKey($rota, $rotas, "{$rota} está em uma lista de exceções, mas não é uma rota registrada");
            $this->assertArrayNotHasKey($rota, $documentadas, "{$rota} está no contrato e em uma lista de exceções: escolha um");
            $this->assertArrayHasKey($operacao, $documentadas, "{$rota} aponta para {$operacao}, que não está no contrato");
        }

        foreach (self::ALIASES as $rota => $operacao) {
            $this->assertSame(
                $rotas[$operacao]->getActionName(),
                $rotas[$rota]->getActionName(),
                "{$rota} não é gêmea de {$operacao}: as rotas levam a actions diferentes",
            );
        }
    }

    public function test_toda_operacao_tem_operation_id_unico_e_tag(): void
    {
        $ids = [];
        foreach ($this->contrato()->paths as $path => $item) {
            foreach ($item->getOperations() as $metodo => $operacao) {
                $chave = strtoupper((string) $metodo).' '.$path;

                $this->assertNotEmpty($operacao->operationId, "{$chave} sem operationId");
                $this->assertMatchesRegularExpression('/^[a-z][A-Za-z0-9]*$/', $operacao->operationId, "operationId de {$chave} deve ser camelCase");
                $this->assertArrayNotHasKey($operacao->operationId, $ids, "operationId {$operacao->operationId} repetido em {$chave}");
                $this->assertNotEmpty($operacao->tags, "{$chave} sem tag");

                $ids[$operacao->operationId] = $chave;
            }
        }
    }

    /**
     * `security: []` no contrato precisa coincidir com as rotas sem o middleware jwt.
     */
    public function test_operacoes_publicas_coincidem_com_as_rotas_sem_jwt(): void
    {
        $rotas = $this->rotasRegistradas();

        foreach ($this->contrato()->paths as $path => $item) {
            foreach ($item->getOperations() as $metodo => $operacao) {
                $chave = strtoupper((string) $metodo).' '.$path;
                $publicaNaApi = ! in_array('jwt', $rotas[$chave]->gatherMiddleware(), true);

                $this->assertSame(
                    $publicaNaApi,
                    $operacao->security === [],
                    "{$chave}: ".($publicaNaApi
                        ? 'a rota é pública (sem o middleware jwt), mas o contrato não traz `security: []`'
                        : 'o contrato traz `security: []`, mas a rota exige token (middleware jwt)'),
                );
            }
        }
    }

    private function contrato(): OpenApi
    {
        return self::$contrato ??= Reader::readFromYamlFile((string) realpath(self::ARQUIVO));
    }

    /**
     * @return array<string, true>
     */
    private function operacoesDocumentadas(): array
    {
        return array_fill_keys(self::operacoesDoContrato($this->contrato()), true);
    }

    /**
     * Rotas da aplicação, sem a de fallback e sem o HEAD que o Laravel acrescenta a todo GET.
     *
     * @return array<string, Route> "MÉTODO /template" => rota
     */
    private function rotasRegistradas(): array
    {
        $rotas = [];
        foreach ($this->app['router']->getRoutes()->getRoutes() as $rota) {
            if ($rota->isFallback) {
                continue;
            }
            foreach ($rota->methods() as $metodo) {
                if ($metodo !== 'HEAD') {
                    $rotas[$metodo.' '.self::template($rota->uri())] = $rota;
                }
            }
        }

        return $rotas;
    }
}

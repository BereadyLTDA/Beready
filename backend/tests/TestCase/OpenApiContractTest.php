<?php

declare(strict_types=1);

namespace App\Test\TestCase;

use App\Application;
use App\Middleware\JwtAuthMiddleware;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;

/**
 * Garante que as rotas (config/routes.php) e o contrato OpenAPI (backend/openapi.yaml) não divergem:
 * toda rota registrada está documentada (ou em uma das listas abaixo, com o motivo) e toda operação
 * documentada tem uma rota. O formato das respostas é conferido pelo ApiTestCase a cada requisição.
 *
 * As chaves são sempre "MÉTODO /template", com o template exatamente como em config/routes.php
 * (o `{id}` do Cake é o mesmo do OpenAPI).
 */
class OpenApiContractTest extends TestCase
{
    public const ARQUIVO = __DIR__ . '/../../openapi.yaml';

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

        // Usuários: o frontend não chama nenhuma das duas leituras por id. A forma REST (/users/{id})
        // não pode ser a documentada porque, para o OpenAPI, tem o mesmo template de /users/{uuid}
        // (outra action, com outra resposta), e um contrato não pode ter os dois.
        'GET /users/{id}' => 'GET /users/view/{id}',
        // O frontend atualiza o perfil com PUT; a mesma rota também aceita POST
        'POST /users/update/{id}' => 'PUT /users/update/{id}',

        // Planos (admin): o frontend usa PUT e DELETE; as mesmas rotas também aceitam POST
        'POST /admin/planos/edit/{id}' => 'PUT /admin/planos/edit/{id}',
        'POST /admin/planos/delete/{id}' => 'DELETE /admin/planos/delete/{id}',
    ];

    /**
     * Rotas com action própria que o OpenAPI não consegue descrever em separado: o template é
     * idêntico ao de uma operação documentada (só muda o padrão do parâmetro). Ficam descritas
     * dentro dela, que aceita as duas formas do parâmetro e tem a mesma resposta.
     *
     * rota => operação documentada que a descreve
     */
    public const MESMO_TEMPLATE = [
        // Flashcards::viewByUuid devolve o mesmo flashcard de Flashcards::view; em
        // GET /flashcards/{id} o parâmetro aceita o id numérico ou o UUID.
        'GET /flashcards/{uuid}' => 'GET /flashcards/{id}',
    ];

    /**
     * PENDENTES: rotas em desenvolvimento, que ainda vão entrar no contrato. Podem ou não já estar
     * registradas. Ao documentar uma delas no openapi.yaml, remova-a daqui (o teste avisa).
     */
    public const PENDENTES = [];

    /**
     * Rota de fallback (Users::notFound, 404 para qualquer path desconhecido): não é uma operação.
     */
    private const FALLBACK = '/*';

    private static ?OpenApi $contrato = null;

    /**
     * Operação do contrato cujo schema vale para a rota: ela mesma, ou a que as listas indicam.
     */
    public static function operacaoEquivalente(string $rota): string
    {
        return self::ALIASES[$rota] ?? self::MESMO_TEMPLATE[$rota] ?? $rota;
    }

    /**
     * @return array<string> "MÉTODO /template" de cada operação do contrato
     */
    public static function operacoesDoContrato(OpenApi $contrato): array
    {
        $operacoes = [];
        foreach ($contrato->paths as $path => $item) {
            foreach (array_keys($item->getOperations()) as $metodo) {
                $operacoes[] = strtoupper((string)$metodo) . ' ' . $path;
            }
        }

        return $operacoes;
    }

    public function testContratoEhUmOpenApiValido(): void
    {
        $contrato = $this->contrato();

        $this->assertSame('3.0.3', $contrato->openapi);
        $this->assertTrue($contrato->validate(), "openapi.yaml inválido:\n- " . implode("\n- ", $contrato->getErrors()));
    }

    public function testTodaRotaEstaNoContratoOuEmUmaDasListas(): void
    {
        $documentadas = $this->operacoesDocumentadas();
        $conhecidas = $documentadas
            + array_fill_keys(array_keys(self::ALIASES), true)
            + array_fill_keys(array_keys(self::MESMO_TEMPLATE), true)
            + array_fill_keys(self::PENDENTES, true);

        $faltando = array_keys(array_diff_key($this->rotasRegistradas(), $conhecidas));
        sort($faltando);

        $this->assertSame(
            [],
            $faltando,
            "Rotas de config/routes.php sem operação em backend/openapi.yaml.\n"
            . 'Documente-as no contrato ou, se forem gêmeas de uma operação já documentada, inclua-as em '
            . self::class . '::ALIASES.'
        );
    }

    public function testTodaOperacaoDoContratoTemRota(): void
    {
        $semRota = array_keys(array_diff_key($this->operacoesDocumentadas(), $this->rotasRegistradas()));
        sort($semRota);

        $this->assertSame(
            [],
            $semRota,
            'Operações de backend/openapi.yaml sem rota em config/routes.php (método + template idênticos).'
        );
    }

    /**
     * As listas não podem acumular entradas mortas nem esconder uma rota que não é gêmea de verdade.
     */
    public function testListasDeExcecoesEstaoCoerentes(): void
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
                $rotas[$operacao],
                $rotas[$rota],
                "{$rota} não é gêmea de {$operacao}: as rotas levam a actions diferentes"
            );
        }

        foreach (self::PENDENTES as $rota) {
            $this->assertArrayNotHasKey(
                $rota,
                $documentadas,
                "{$rota} já está no contrato: remova-a de " . self::class . '::PENDENTES'
            );
        }
    }

    public function testTodaOperacaoTemOperationIdUnicoETag(): void
    {
        $ids = [];
        foreach ($this->contrato()->paths as $path => $item) {
            foreach ($item->getOperations() as $metodo => $operacao) {
                $chave = strtoupper((string)$metodo) . ' ' . $path;

                $this->assertNotEmpty($operacao->operationId, "{$chave} sem operationId");
                $this->assertMatchesRegularExpression('/^[a-z][A-Za-z0-9]*$/', $operacao->operationId, "operationId de {$chave} deve ser camelCase");
                $this->assertArrayNotHasKey($operacao->operationId, $ids, "operationId {$operacao->operationId} repetido em {$chave}");
                $this->assertNotEmpty($operacao->tags, "{$chave} sem tag");

                $ids[$operacao->operationId] = $chave;
            }
        }
    }

    /**
     * `security: []` no contrato precisa coincidir com as rotas públicas do JwtAuthMiddleware.
     */
    public function testOperacoesPublicasCoincidemComOJwtAuthMiddleware(): void
    {
        $constante = static fn (string $nome): array => (new \ReflectionClassConstant(JwtAuthMiddleware::class, $nome))->getValue();
        $paths = $constante('PUBLIC_PATHS');
        $prefixos = $constante('PUBLIC_PREFIXES');
        $pathsGet = $constante('PUBLIC_GET_PATHS');

        foreach ($this->contrato()->paths as $path => $item) {
            foreach ($item->getOperations() as $metodo => $operacao) {
                $metodo = strtoupper((string)$metodo);

                $publicaNaApi = in_array($path, $paths, true) || ($metodo === 'GET' && in_array($path, $pathsGet, true));
                foreach ($prefixos as $prefixo) {
                    $publicaNaApi = $publicaNaApi || str_starts_with($path, $prefixo);
                }

                $this->assertSame(
                    $publicaNaApi,
                    $operacao->security === [],
                    "{$metodo} {$path}: " . ($publicaNaApi
                        ? 'a rota é pública no JwtAuthMiddleware, mas o contrato não traz `security: []`'
                        : 'o contrato traz `security: []`, mas a rota exige token no JwtAuthMiddleware')
                );
            }
        }
    }

    private function contrato(): OpenApi
    {
        return self::$contrato ??= Reader::readFromYamlFile((string)realpath(self::ARQUIVO));
    }

    /**
     * @return array<string, true>
     */
    private function operacoesDocumentadas(): array
    {
        return array_fill_keys(self::operacoesDoContrato($this->contrato()), true);
    }

    /**
     * Rotas da aplicação, sem a de fallback.
     *
     * @return array<string, string> "MÉTODO /template" => "Controller::action"
     */
    private function rotasRegistradas(): array
    {
        Router::reload();
        (new Application(CONFIG))->routes(Router::createRouteBuilder('/'));

        $rotas = [];
        foreach (Router::routes() as $rota) {
            if ($rota->template === self::FALLBACK) {
                continue;
            }

            $metodos = (array)($rota->defaults['_method'] ?? []);
            $this->assertNotEmpty($metodos, "Rota {$rota->template} sem método HTTP definido: o contrato não tem como descrevê-la");

            foreach ($metodos as $metodo) {
                $rotas[strtoupper($metodo) . ' ' . $rota->template] = $rota->defaults['controller'] . '::' . $rota->defaults['action'];
            }
        }

        return $rotas;
    }
}

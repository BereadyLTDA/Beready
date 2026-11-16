<?php

declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use App\Contracts\GeradorTextoInterface;
use App\Exceptions\GeracaoException;
use App\Test\TestCase\ApiTestCase;
use App\Test\TestCase\FakeGeradorTexto;
use Cake\Cache\Cache;
use Cake\Datasource\EntityInterface;

/**
 * Geração de traduções e frases por IA (POST /prompts/{id}/traducoes/gerar e .../frases/gerar).
 * O provedor é um fake: o que se testa é a regra em volta da chamada.
 */
class GeracaoIaTest extends ApiTestCase
{
    private EntityInterface $user;
    private EntityInterface $prompt;
    private FakeGeradorTexto $gerador;

    protected function setUp(): void
    {
        parent::setUp();

        // O uso diário da IA não pode vazar de um teste para outro
        Cache::clear('ia_uso');

        $this->user = $this->createUser();
        $this->prompt = $this->insert('Prompts', [
            'usuario_id' => $this->user->id,
            'texto_original' => 'Where is the station?',
            'idioma_original' => 'en-US',
            'contexto' => 'viagem',
        ]);

        $this->gerador = new FakeGeradorTexto();
        $this->mockService(GeradorTextoInterface::class, fn () => $this->gerador);
        $this->actingAs($this->user);
    }

    private function traducoesDoPrompt(): array
    {
        return $this->getTableLocator()->get('Traducoes')->find()
            ->where(['prompt_id' => $this->prompt->id])
            ->all()
            ->toList();
    }

    private function frasesDoPrompt(): array
    {
        return $this->getTableLocator()->get('FrasesSemelhantes')->find()
            ->where(['prompt_id' => $this->prompt->id])
            ->orderBy(['id' => 'ASC'])
            ->all()
            ->toList();
    }

    public function testGeraEGravaATraducaoDoPrompt(): void
    {
        $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);

        $this->assertResponseCode(201);
        $data = $this->responseJson()['data'];
        $this->assertSame('Onde fica a estação?', $data['texto_traduzido']);
        $this->assertSame('pt-BR', $data['idioma_destino']);
        $this->assertSame('claude', $data['servico_traducao']);

        // O provedor recebe o texto, os idiomas e o contexto do prompt
        $this->assertSame([[
            'texto' => 'Where is the station?',
            'idiomaOrigem' => 'en-US',
            'idiomaDestino' => 'pt-BR',
            'contexto' => 'viagem',
        ]], $this->gerador->chamadas);

        $gravadas = $this->traducoesDoPrompt();
        $this->assertCount(1, $gravadas);
        $this->assertSame('Onde fica a estação?', $gravadas[0]->texto_traduzido);
        $this->assertEqualsWithDelta(0.97, (float)$gravadas[0]->pontuacao_confianca, 0.001);

        // E aparece na listagem que o frontend já usa
        $this->get("/traducoes/prompt/{$this->prompt->id}");
        $this->assertResponseOk();
        $this->assertCount(1, $this->responseJson()['data']);
    }

    public function testIdiomaDeDestinoInvalidoNaoChamaAIa(): void
    {
        foreach ([[], ['idioma_destino' => ''], ['idioma_destino' => 'português do Brasil']] as $body) {
            $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", $body);

            $this->assertResponseCode(422);
            $this->assertArrayHasKey('idioma_destino', $this->responseJson()['errors']);
        }

        $this->assertSame([], $this->gerador->chamadas);
        $this->assertSame([], $this->traducoesDoPrompt());
    }

    public function testGeraEGravaAsFrasesComAQuantidadePedida(): void
    {
        $this->postJson("/prompts/{$this->prompt->id}/frases/gerar", ['quantidade' => 3]);

        $this->assertResponseCode(201);
        $this->assertCount(3, $this->responseJson()['data']);
        $this->assertSame(3, $this->gerador->chamadas[0]['quantidade']);

        $gravadas = $this->frasesDoPrompt();
        $this->assertSame(
            ['Frase gerada 1', 'Frase gerada 2', 'Frase gerada 3'],
            array_map(fn ($f) => $f->frase_semelhante, $gravadas),
        );
        $this->assertSame('alternativa', $gravadas[0]->tipo_frase);
        $this->assertSame('iniciante', $gravadas[0]->nivel_dificuldade);
    }

    public function testQuantidadeDeFrasesTemPadraoELimites(): void
    {
        $this->postJson("/prompts/{$this->prompt->id}/frases/gerar");
        $this->assertResponseCode(201);
        $this->assertCount(5, $this->responseJson()['data']);

        foreach ([0, 11, 'muitas'] as $quantidade) {
            $this->postJson("/prompts/{$this->prompt->id}/frases/gerar", ['quantidade' => $quantidade]);
            $this->assertResponseCode(422);
        }

        $this->assertCount(1, $this->gerador->chamadas);
    }

    public function testPromptDeOutroUsuarioResponde404SemChamarAIa(): void
    {
        $outro = $this->createUser();
        $this->actingAs($outro);

        $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);
        $this->assertResponseCode(404);

        $this->postJson("/prompts/{$this->prompt->id}/frases/gerar");
        $this->assertResponseCode(404);

        $this->postJson('/prompts/999999/traducoes/gerar', ['idioma_destino' => 'pt-BR']);
        $this->assertResponseCode(404);

        $this->assertSame([], $this->gerador->chamadas);
    }

    public function testExigeAutenticacao(): void
    {
        $this->withBearer(null);

        $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);

        $this->assertResponseCode(401);
        $this->assertSame([], $this->gerador->chamadas);
    }

    public function testPromptSemTextoOuLongoDemaisResponde422(): void
    {
        $vazio = $this->insert('Prompts', ['usuario_id' => $this->user->id, 'texto_original' => '   ']);
        $this->postJson("/prompts/{$vazio->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);
        $this->assertResponseCode(422);

        $longo = $this->insert('Prompts', [
            'usuario_id' => $this->user->id,
            'texto_original' => str_repeat('a', 2001),
        ]);
        $this->postJson("/prompts/{$longo->id}/frases/gerar");
        $this->assertResponseCode(422);

        $this->assertSame([], $this->gerador->chamadas);
    }

    public function testFalhaDoProvedorDevolveOStatusDaFalhaENaoGravaNada(): void
    {
        $this->gerador->falha = GeracaoException::indisponivel();
        $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);
        $this->assertResponseCode(503);
        $this->assertSame('O serviço de IA está ocupado. Tente novamente em instantes', $this->responseJson()['message']);

        $this->gerador->falha = GeracaoException::recusada();
        $this->postJson("/prompts/{$this->prompt->id}/frases/gerar");
        $this->assertResponseCode(422);

        $this->assertSame([], $this->traducoesDoPrompt());
        $this->assertSame([], $this->frasesDoPrompt());
    }

    public function testLimiteDiarioPorUsuario(): void
    {
        $this->setEnv('IA_LIMITE_DIARIO', '2');

        $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);
        $this->assertResponseCode(201);
        $this->postJson("/prompts/{$this->prompt->id}/frases/gerar", ['quantidade' => 1]);
        $this->assertResponseCode(201);

        // A terceira do dia é barrada antes de chegar ao provedor
        $this->postJson("/prompts/{$this->prompt->id}/traducoes/gerar", ['idioma_destino' => 'es-ES']);
        $this->assertResponseCode(429);
        $this->assertCount(2, $this->gerador->chamadas);

        // O teto é de cada usuário
        $outro = $this->createUser();
        $promptDoOutro = $this->insert('Prompts', ['usuario_id' => $outro->id, 'texto_original' => 'Good morning']);
        $this->actingAs($outro);
        $this->postJson("/prompts/{$promptDoOutro->id}/traducoes/gerar", ['idioma_destino' => 'pt-BR']);
        $this->assertResponseCode(201);
    }
}

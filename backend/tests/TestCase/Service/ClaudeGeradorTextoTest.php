<?php

declare(strict_types=1);

namespace App\Test\TestCase\Service;

use App\Exceptions\GeracaoException;
use App\Services\ClaudeGeradorTexto;
use Cake\TestSuite\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

/**
 * Integração com a API da Anthropic, com o HTTP simulado: confere a requisição montada pelo SDK
 * e como cada tipo de resposta é tratado. Nenhuma chamada real é feita.
 */
class ClaudeGeradorTextoTest extends TestCase
{
    /**
     * @var array<int, array{request: \Psr\Http\Message\RequestInterface}>
     */
    private array $enviadas = [];

    private ?string $chaveOriginal = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chaveOriginal = getenv('ANTHROPIC_API_KEY') === false ? null : (string)getenv('ANTHROPIC_API_KEY');
        $this->definirChave('chave-de-teste');
    }

    protected function tearDown(): void
    {
        $this->definirChave($this->chaveOriginal);

        parent::tearDown();
    }

    private function definirChave(?string $valor): void
    {
        if ($valor === null) {
            unset($_SERVER['ANTHROPIC_API_KEY'], $_ENV['ANTHROPIC_API_KEY']);
            putenv('ANTHROPIC_API_KEY');

            return;
        }

        $_SERVER['ANTHROPIC_API_KEY'] = $_ENV['ANTHROPIC_API_KEY'] = $valor;
        putenv("ANTHROPIC_API_KEY={$valor}");
    }

    /**
     * @param list<\GuzzleHttp\Psr7\Response> $respostas
     */
    private function gerador(array $respostas): ClaudeGeradorTexto
    {
        $stack = HandlerStack::create(new MockHandler($respostas));
        $stack->push(Middleware::history($this->enviadas));

        // http_errors desligado: é o SDK, não o Guzzle, que transforma 4xx/5xx em exceção
        return new ClaudeGeradorTexto(new Client(['handler' => $stack, 'http_errors' => false]));
    }

    /**
     * Resposta da API de Messages com um bloco de texto.
     *
     * @param array<string, mixed> $extra
     */
    private function mensagem(string $texto, array $extra = []): Response
    {
        return new Response(200, ['Content-Type' => 'application/json'], (string)json_encode($extra + [
            'id' => 'msg_teste',
            'type' => 'message',
            'role' => 'assistant',
            'model' => 'claude-opus-5-5',
            'content' => [['type' => 'text', 'text' => $texto]],
            'stop_reason' => 'end_turn',
            'stop_sequence' => null,
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ]));
    }

    private function erro(int $status, string $tipo): Response
    {
        return new Response($status, ['Content-Type' => 'application/json'], (string)json_encode([
            'type' => 'error',
            'error' => ['type' => $tipo, 'message' => 'erro simulado'],
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    private function corpoEnviado(int $indice = 0): array
    {
        return (array)json_decode((string)$this->enviadas[$indice]['request']->getBody(), true);
    }

    public function testTraduzirMontaARequisicaoELeAResposta(): void
    {
        $gerador = $this->gerador([$this->mensagem((string)json_encode([
            'texto_traduzido' => ' Onde fica a estação? ',
            'traducoes_alternativas' => ['Onde é a estação?', '  '],
            'pontuacao_confianca' => 1.4,
        ]))]);

        $resultado = $gerador->traduzir('Where is the station?', 'en-US', 'pt-BR', 'viagem');

        // Espaços aparados, alternativa vazia descartada e confiança limitada a 0..1
        $this->assertSame([
            'texto_traduzido' => 'Onde fica a estação?',
            'traducoes_alternativas' => ['Onde é a estação?'],
            'pontuacao_confianca' => 1.0,
        ], $resultado);

        $request = $this->enviadas[0]['request'];
        $this->assertSame('POST', $request->getMethod());
        $this->assertStringEndsWith('/v1/messages', $request->getUri()->getPath());
        $this->assertSame('Bearer chave-de-teste', $request->getHeaderLine('Authorization'));
        $this->assertStringContainsString('server-side-fallback-2026-07-01', $request->getHeaderLine('anthropic-beta'));

        $corpo = $this->corpoEnviado();
        $this->assertSame('claude-opus-5-5', $corpo['model']);
        $this->assertSame('default', $corpo['fallbacks']);
        $this->assertSame('low', $corpo['output_config']['effort']);
        $this->assertSame('json_schema', $corpo['output_config']['format']['type']);
        $this->assertSame(
            ['texto_traduzido', 'traducoes_alternativas', 'pontuacao_confianca'],
            $corpo['output_config']['format']['schema']['required'],
        );
        $this->assertArrayNotHasKey('thinking', $corpo);
        $this->assertArrayNotHasKey('temperature', $corpo);

        // O texto do usuário vai na mensagem, separado das instruções (system)
        $this->assertSame('user', $corpo['messages'][0]['role']);
        $this->assertStringContainsString('Where is the station?', $corpo['messages'][0]['content']);
        $this->assertStringContainsString('pt-BR', $corpo['messages'][0]['content']);
        $this->assertStringContainsString('viagem', $corpo['messages'][0]['content']);
        $this->assertStringNotContainsString('Where is the station?', $corpo['system']);
    }

    public function testFrasesSemelhantesRespeitaAQuantidadeEDescartaVazias(): void
    {
        $frase = fn (string $texto) => [
            'frase_semelhante' => $texto,
            'tipo_frase' => 'sinonimo',
            'nivel_dificuldade' => 'intermediario',
            'pontuacao_semelhante' => 0.756,
        ];
        $gerador = $this->gerador([$this->mensagem((string)json_encode([
            'frases' => [$frase('Uma'), $frase(''), $frase('Duas'), $frase('Três')],
        ]))]);

        $frases = $gerador->frasesSemelhantes('Texto', 'pt-BR', 2, null);

        $this->assertSame(['Uma', 'Duas'], array_column($frases, 'frase_semelhante'));
        $this->assertSame(0.76, $frases[0]['pontuacao_semelhante']);

        $schema = $this->corpoEnviado()['output_config']['format']['schema'];
        $item = $schema['properties']['frases']['items'];
        $this->assertSame(['alternativa', 'sinonimo', 'exemplo', 'relacionada'], $item['properties']['tipo_frase']['enum']);
        $this->assertFalse($item['additionalProperties']);
    }

    public function testSemChaveNemChegaAChamarAApi(): void
    {
        $this->definirChave(null);
        $gerador = $this->gerador([]);

        try {
            $gerador->traduzir('Texto', 'en', 'pt-BR', null);
            $this->fail('Deveria ter falhado sem ANTHROPIC_API_KEY');
        } catch (GeracaoException $e) {
            $this->assertSame(503, $e->getCode());
        }

        $this->assertSame([], $this->enviadas);
    }

    public function testRecusaDoModeloViraErro422(): void
    {
        $gerador = $this->gerador([$this->mensagem('', [
            'content' => [],
            'stop_reason' => 'refusal',
            'stop_details' => ['type' => 'refusal', 'category' => null, 'explanation' => null],
        ])]);

        $this->expectException(GeracaoException::class);
        $this->expectExceptionCode(422);

        $gerador->traduzir('Texto', 'en', 'pt-BR', null);
    }

    public function testRespostaCortadaOuSemJsonViraErro502(): void
    {
        foreach ([
            $this->mensagem('{"texto_traduzido": "Ol', ['stop_reason' => 'max_tokens']),
            $this->mensagem('isto não é JSON'),
        ] as $resposta) {
            try {
                $this->gerador([$resposta])->traduzir('Texto', 'en', 'pt-BR', null);
                $this->fail('Deveria ter falhado');
            } catch (GeracaoException $e) {
                $this->assertSame(502, $e->getCode());
            }
        }
    }

    public function testErrosDaApiSaoClassificados(): void
    {
        $casos = [
            // [respostas na ordem, status esperado]: 1 nova tentativa nos erros que valem repetir
            [[$this->erro(401, 'authentication_error')], 503],
            [[$this->erro(400, 'invalid_request_error')], 502],
            [[$this->erro(429, 'rate_limit_error'), $this->erro(429, 'rate_limit_error')], 503],
            [[$this->erro(529, 'overloaded_error'), $this->erro(529, 'overloaded_error')], 503],
        ];

        foreach ($casos as [$respostas, $esperado]) {
            try {
                $this->gerador($respostas)->traduzir('Texto', 'en', 'pt-BR', null);
                $this->fail('Deveria ter falhado');
            } catch (GeracaoException $e) {
                $this->assertSame($esperado, $e->getCode(), 'Status ' . $respostas[0]->getStatusCode());
            }
        }
    }

    public function testErroTemporarioSeguidoDeSucessoERepetidoPeloSdk(): void
    {
        $gerador = $this->gerador([
            $this->erro(529, 'overloaded_error'),
            $this->mensagem((string)json_encode([
                'texto_traduzido' => 'Olá',
                'traducoes_alternativas' => [],
                'pontuacao_confianca' => 0.9,
            ])),
        ]);

        $this->assertSame('Olá', $gerador->traduzir('Hello', 'en', 'pt-BR', null)['texto_traduzido']);
        $this->assertCount(2, $this->enviadas);
    }
}

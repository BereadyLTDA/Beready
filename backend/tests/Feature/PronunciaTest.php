<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PronunciaService;
use Tests\ApiTestCase;

/**
 * Exercício "Repita a frase": frases em inglês do aluno e nota calculada no servidor.
 */
class PronunciaTest extends ApiTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = $this->createUser();
        $this->actingAs($this->user);
    }

    public function test_nota_compara_as_palavras_e_aponta_as_que_faltaram(): void
    {
        $service = new PronunciaService;

        $this->assertSame(['nota' => 100, 'faltando' => []], $service->avaliar('Where is the station?', 'where is the station'));

        $parcial = $service->avaliar('Where is the nearest station?', 'where is the station');
        $this->assertSame(['nearest'], $parcial['faltando']);
        $this->assertSame(89, $parcial['nota']);

        // Contração com apóstrofo curvo conta como a mesma palavra
        $this->assertSame(100, $service->avaliar("I don't know", 'I don’t know')['nota']);

        // Palavras a mais também baixam a nota
        $this->assertLessThan(100, $service->avaliar('Good morning', 'good morning everybody here')['nota']);

        $this->assertSame(['nota' => 0, 'faltando' => ['hello', 'world']], $service->avaliar('Hello world', ''));
    }

    public function test_frases_vem_dos_prompts_em_ingles_frases_e_traducoes_do_aluno(): void
    {
        $emIngles = $this->insert('Prompts', ['usuario_id' => $this->user->id, 'texto_original' => 'Where is the station?', 'idioma_original' => 'en-US']);
        $this->insert('FrasesSemelhantes', ['prompt_id' => $emIngles->id, 'frase_semelhante' => 'How do I get to the station?']);
        $emPortugues = $this->insert('Prompts', ['usuario_id' => $this->user->id, 'texto_original' => 'Onde fica a estação?', 'idioma_original' => 'pt-BR']);
        $this->insert('Traducoes', ['prompt_id' => $emPortugues->id, 'texto_traduzido' => 'Where is the train station?', 'idioma_destino' => 'en-US']);
        // De outro usuário: não pode aparecer
        $outro = $this->createUser();
        $this->insert('Prompts', ['usuario_id' => $outro->id, 'texto_original' => 'Secret sentence of another user', 'idioma_original' => 'en-US']);

        $this->getJson('/pronuncia/frases?limite=10');

        $this->assertResponseOk();
        $frases = $this->responseJson()['data'];
        $textos = array_column($frases, 'texto');
        $this->assertCount(10, $frases);
        $this->assertContains('Where is the station?', $textos);
        $this->assertContains('How do I get to the station?', $textos);
        $this->assertContains('Where is the train station?', $textos);
        $this->assertNotContains('Onde fica a estação?', $textos);
        $this->assertNotContains('Secret sentence of another user', $textos);
        // O restante é completado com frases básicas
        $this->assertContains('basica', array_column($frases, 'origem'));
    }

    public function test_registrar_tentativa_calcula_e_guarda_a_nota(): void
    {
        $this->postJson('/pronuncia/tentativas', ['frase' => 'Nice to meet you.', 'reconhecido' => 'nice to meet']);

        $this->assertResponseCode(201);
        $data = $this->responseJson()['data'];
        $this->assertSame(['you'], $data['faltando']);
        $this->assertSame(86, $data['nota']);
        $this->assertSame(86, $this->tabela('PronunciaTentativas')->where('usuario_id', $this->user->id)->value('nota'));
    }

    public function test_frase_vazia_ou_longa_demais_e_recusada(): void
    {
        $this->postJson('/pronuncia/tentativas', ['frase' => '   ', 'reconhecido' => 'x']);
        $this->assertResponseCode(422);

        $this->postJson('/pronuncia/tentativas', ['frase' => str_repeat('a ', 200), 'reconhecido' => 'a']);
        $this->assertResponseCode(422);
    }

    public function test_resumo_so_conta_as_tentativas_do_proprio_aluno(): void
    {
        $this->postJson('/pronuncia/tentativas', ['frase' => 'Good morning', 'reconhecido' => 'good morning']);
        $this->postJson('/pronuncia/tentativas', ['frase' => 'Good night', 'reconhecido' => 'good']);
        $outro = $this->createUser();
        $this->insert('PronunciaTentativas', ['usuario_id' => $outro->id, 'frase' => 'x y', 'reconhecido' => 'x y', 'nota' => 100]);

        $this->getJson('/pronuncia/resumo');

        $this->assertResponseOk();
        $resumo = $this->responseJson()['data'];
        $this->assertSame(2, $resumo['total']);
        $this->assertSame(100, $resumo['melhor']);
        $this->assertSame(84, $resumo['media']); // (100 + 67) / 2
        $this->assertCount(2, $resumo['recentes']);
    }
}

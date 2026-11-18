<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Tests\ApiTestCase;

/**
 * IDOR: um usuário comum (A) não pode ler/alterar/excluir dados de outro usuário (B).
 * Usuários respondem 403; recursos de outro dono respondem 404, como se não existissem.
 */
class OwnershipTest extends ApiTestCase
{
    private Model $userA;

    private Model $userB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userA = $this->createUser();
        $this->userB = $this->createUser();
        $this->actingAs($this->userA);
    }

    // ---------------------------------------------------------------- usuários

    public function test_nao_ve_outro_usuario(): void
    {
        $this->get('/users/'.$this->userB->id);
        $this->assertResponseCode(403);
        $this->assertArrayNotHasKey('data', $this->responseJson());

        $this->get('/users/view/'.$this->userB->id);
        $this->assertResponseCode(403);

        $this->get('/users/'.$this->userB->uuid);
        $this->assertResponseCode(403);
    }

    public function test_nao_edita_outro_usuario(): void
    {
        $this->putJson('/users/update/'.$this->userB->id, ['nome' => 'Invadido', 'email' => 'invadido@teste.local']);

        $this->assertResponseCode(403);
        $userB = $this->fetch('Users', $this->userB->id);
        $this->assertSame($this->userB->nome, $userB->nome);
        $this->assertSame($this->userB->email, $userB->email);
    }

    public function test_nao_exclui_outro_usuario(): void
    {
        $this->delete('/users/delete/'.$this->userB->id);

        $this->assertResponseCode(403);
        $this->assertNotNull($this->fetch('Users', $this->userB->id));
    }

    public function test_ve_o_proprio_usuario(): void
    {
        $this->get('/users/'.$this->userA->id);

        $this->assertResponseOk();
        $this->assertSame($this->userA->id, $this->responseJson()['data']['user']['id']);
    }

    public function test_update_do_proprio_usuario_ignora_campos_protegidos(): void
    {
        $senhaHashOriginal = $this->userA->senha_hash;

        $this->putJson('/users/update/'.$this->userA->id, [
            'nome' => 'Nome Novo',
            'role' => 'admin',
            'status' => 'bloqueado',
            'senha_hash' => 'hash-forjado',
            'uuid' => '00000000-0000-4000-8000-000000000000',
        ]);

        $this->assertResponseOk();
        $user = $this->fetch('Users', $this->userA->id);
        $this->assertSame('Nome Novo', $user->nome);
        $this->assertSame('user', $user->role);
        $this->assertSame('ativo', $user->status);
        $this->assertSame($senhaHashOriginal, $user->senha_hash);
        $this->assertSame($this->userA->uuid, $user->uuid);
    }

    public function test_update_do_proprio_usuario_troca_senha_pelo_campo_senha(): void
    {
        $this->putJson('/users/update/'.$this->userA->id, ['senha' => 'novaSenha123']);

        $this->assertResponseOk();
        $user = $this->fetch('Users', $this->userA->id);
        $this->assertTrue(password_verify('novaSenha123', $user->senha_hash));
        $this->assertArrayNotHasKey('senha_hash', $this->responseJson()['data']['user']);
    }

    // -------------------------------------------------------------- flashcards

    public function test_flashcard_de_outro_usuario_responde404_e_fica_intacto(): void
    {
        $flashcard = $this->insert('Flashcards', [
            'usuario_id' => $this->userB->id,
            'frente' => 'Frente B',
            'verso' => 'Verso B',
        ]);
        $uuid = $this->fetch('Flashcards', $flashcard->id)->uuid;

        $this->get('/flashcards/'.$flashcard->id);
        $this->assertResponseCode(404);
        $this->assertArrayNotHasKey('data', $this->responseJson());

        $this->get('/flashcards/'.$uuid);
        $this->assertResponseCode(404);

        $this->putJson('/flashcards/'.$flashcard->id, ['frente' => 'Alterada']);
        $this->assertResponseCode(404);

        $this->delete('/flashcards/'.$flashcard->id);
        $this->assertResponseCode(404);

        $atual = $this->fetch('Flashcards', $flashcard->id);
        $this->assertNotNull($atual);
        $this->assertSame('Frente B', $atual->frente);
        $this->assertSame($this->userB->id, $atual->usuario_id);
    }

    public function test_listagem_de_flashcards_traz_so_os_do_usuario(): void
    {
        $this->insert('Flashcards', ['usuario_id' => $this->userA->id, 'frente' => 'A1', 'verso' => 'v']);
        $this->insert('Flashcards', ['usuario_id' => $this->userA->id, 'frente' => 'A2', 'verso' => 'v']);
        $this->insert('Flashcards', ['usuario_id' => $this->userB->id, 'frente' => 'B1', 'verso' => 'v']);

        $this->get('/flashcards');

        $this->assertResponseOk();
        $data = $this->responseJson()['data'];
        $this->assertCount(2, $data);
        $this->assertSame([$this->userA->id], array_values(array_unique(array_column($data, 'usuario_id'))));
    }

    public function test_criar_flashcard_ignora_usuario_id_do_corpo(): void
    {
        $this->postJson('/flashcards', [
            'frente' => 'Pergunta',
            'verso' => 'Resposta',
            'usuario_id' => $this->userB->id,
        ]);

        $this->assertResponseCode(201);
        $flashcard = $this->fetch('Flashcards', $this->responseJson()['data']['id']);
        $this->assertSame($this->userA->id, $flashcard->usuario_id);
    }

    public function test_editar_flashcard_proprio_nao_transfere_para_outro_usuario(): void
    {
        $flashcard = $this->insert('Flashcards', ['usuario_id' => $this->userA->id, 'frente' => 'A', 'verso' => 'v']);

        $this->putJson('/flashcards/'.$flashcard->id, ['frente' => 'A editada', 'usuario_id' => $this->userB->id]);

        $this->assertResponseOk();
        $atual = $this->fetch('Flashcards', $flashcard->id);
        $this->assertSame('A editada', $atual->frente);
        $this->assertSame($this->userA->id, $atual->usuario_id);
    }

    // ------------------------------------------------------------------ quizes

    public function test_quiz_privado_de_outro_usuario_responde404_e_fica_intacto(): void
    {
        $quiz = $this->insert('Quizes', [
            'usuario_id' => $this->userB->id,
            'titulo' => 'Quiz B',
            'publico' => false,
        ]);

        $this->get('/quizes/'.$quiz->id);
        $this->assertResponseCode(404);

        $this->putJson('/quizes/'.$quiz->id, ['titulo' => 'Alterado']);
        $this->assertResponseCode(404);

        $this->delete('/quizes/'.$quiz->id);
        $this->assertResponseCode(404);

        $atual = $this->fetch('Quizes', $quiz->id);
        $this->assertNotNull($atual);
        $this->assertSame('Quiz B', $atual->titulo);
    }

    public function test_quiz_publico_de_outro_usuario_pode_ser_lido_mas_nao_alterado(): void
    {
        $quiz = $this->insert('Quizes', [
            'usuario_id' => $this->userB->id,
            'titulo' => 'Quiz público B',
            'publico' => true,
        ]);

        $this->get('/quizes/'.$quiz->id);
        $this->assertResponseOk();

        $this->putJson('/quizes/'.$quiz->id, ['titulo' => 'Alterado']);
        $this->assertResponseCode(404);

        $this->delete('/quizes/'.$quiz->id);
        $this->assertResponseCode(404);

        $this->assertSame('Quiz público B', $this->fetch('Quizes', $quiz->id)->titulo);
    }

    public function test_listagem_de_quizes_traz_so_os_do_usuario(): void
    {
        $this->insert('Quizes', ['usuario_id' => $this->userA->id, 'titulo' => 'A1']);
        $this->insert('Quizes', ['usuario_id' => $this->userB->id, 'titulo' => 'B1', 'publico' => true]);

        $this->get('/quizes');

        $this->assertResponseOk();
        $this->assertSame(['A1'], array_column($this->responseJson()['data'], 'titulo'));
    }

    public function test_criar_quiz_ignora_usuario_id_do_corpo(): void
    {
        $this->postJson('/quizes', ['titulo' => 'Meu quiz', 'usuario_id' => $this->userB->id]);

        $this->assertResponseCode(201);
        $quiz = $this->fetch('Quizes', $this->responseJson()['data']['id']);
        $this->assertSame($this->userA->id, $quiz->usuario_id);
    }

    // ----------------------------------------------------------------- prompts

    public function test_prompt_de_outro_usuario_responde404_e_fica_intacto(): void
    {
        $prompt = $this->insert('Prompts', [
            'usuario_id' => $this->userB->id,
            'texto_original' => 'Texto B',
        ]);

        $this->get('/prompts/'.$prompt->id);
        $this->assertResponseCode(404);

        $this->putJson('/prompts/'.$prompt->id, ['texto_original' => 'Alterado']);
        $this->assertResponseCode(404);

        $this->delete('/prompts/'.$prompt->id);
        $this->assertResponseCode(404);

        $atual = $this->fetch('Prompts', $prompt->id);
        $this->assertNotNull($atual);
        $this->assertSame('Texto B', $atual->texto_original);
    }

    public function test_prompts_por_usuario_de_outro_usuario_responde403(): void
    {
        $this->insert('Prompts', ['usuario_id' => $this->userB->id, 'texto_original' => 'Texto B']);

        $this->get('/prompts/usuario/'.$this->userB->id);

        $this->assertResponseCode(403);
        $this->assertArrayNotHasKey('data', $this->responseJson());
    }

    public function test_listagem_de_prompts_traz_so_os_do_usuario(): void
    {
        $this->insert('Prompts', ['usuario_id' => $this->userA->id, 'texto_original' => 'A1']);
        $this->insert('Prompts', ['usuario_id' => $this->userB->id, 'texto_original' => 'B1']);

        $this->get('/prompts');

        $this->assertResponseOk();
        $this->assertSame(['A1'], array_column($this->responseJson()['data'], 'texto_original'));
    }

    public function test_criar_prompt_ignora_usuario_id_do_corpo(): void
    {
        $this->postJson('/prompts', ['texto_original' => 'Olá', 'usuario_id' => $this->userB->id]);

        $this->assertResponseCode(201);
        $prompt = $this->fetch('Prompts', $this->responseJson()['data']['id']);
        $this->assertSame($this->userA->id, $prompt->usuario_id);
    }

    // -------------------------------------------------------------------- tags

    public function test_tag_de_outro_usuario_responde404_e_fica_intacta(): void
    {
        $tag = $this->insert('Tags', ['criado_por' => $this->userB->id, 'nome' => 'tag-b', 'tag_sistema' => false]);

        $this->get('/tags/'.$tag->id);
        $this->assertResponseCode(404);

        $this->putJson('/tags/'.$tag->id, ['nome' => 'alterada']);
        $this->assertResponseCode(404);

        $this->delete('/tags/'.$tag->id);
        $this->assertResponseCode(404);

        $atual = $this->fetch('Tags', $tag->id);
        $this->assertNotNull($atual);
        $this->assertSame('tag-b', $atual->nome);
    }

    public function test_tags_por_usuario_de_outro_usuario_responde403(): void
    {
        $this->insert('Tags', ['criado_por' => $this->userB->id, 'nome' => 'tag-b']);

        $this->get('/tags/usuario/'.$this->userB->id);

        $this->assertResponseCode(403);
        $this->assertArrayNotHasKey('data', $this->responseJson());
    }

    public function test_listagem_de_tags_traz_as_do_usuario_e_as_de_sistema(): void
    {
        $this->insert('Tags', ['criado_por' => $this->userA->id, 'nome' => 'tag-a']);
        $this->insert('Tags', ['criado_por' => $this->userB->id, 'nome' => 'tag-b']);
        $this->insert('Tags', ['criado_por' => null, 'nome' => 'tag-sistema', 'tag_sistema' => true]);

        $this->get('/tags');

        $this->assertResponseOk();
        $this->assertSame(['tag-a', 'tag-sistema'], array_column($this->responseJson()['data'], 'nome'));
    }

    public function test_criar_tag_ignora_criado_por_e_tag_sistema_do_corpo(): void
    {
        $this->postJson('/tags', ['nome' => 'minha-tag', 'criado_por' => $this->userB->id, 'tag_sistema' => true]);

        $this->assertResponseCode(201);
        $tag = $this->fetch('Tags', $this->responseJson()['data']['id']);
        $this->assertSame($this->userA->id, $tag->criado_por);
        $this->assertFalse((bool) $tag->tag_sistema);
    }

    public function test_nome_de_tag_e_unico_por_usuario(): void
    {
        $this->insert('Tags', ['criado_por' => $this->userB->id, 'nome' => 'verbos']);

        // Mesmo nome da tag de outro usuário: permitido
        $this->postJson('/tags', ['nome' => 'verbos']);
        $this->assertResponseCode(201);

        // Repetir o próprio nome: conflito
        $this->postJson('/tags', ['nome' => 'verbos']);
        $this->assertResponseCode(409);
    }

    // --------------------------------------------------------------- progresso

    public function test_progresso_de_outro_usuario_responde403(): void
    {
        $this->get('/progresso/usuario/'.$this->userB->id);

        $this->assertResponseCode(403);
        $this->assertArrayNotHasKey('data', $this->responseJson());
    }

    public function test_progresso_do_proprio_usuario(): void
    {
        $this->get('/progresso/usuario/'.$this->userA->id);

        $this->assertResponseOk();
    }

    // ------------------------------------------------------------------- admin

    public function test_admin_acessa_recursos_de_outro_usuario(): void
    {
        $admin = $this->createUser('admin');
        $flashcard = $this->insert('Flashcards', ['usuario_id' => $this->userB->id, 'frente' => 'B', 'verso' => 'v']);

        $this->actingAs($admin);

        $this->get('/users/'.$this->userB->id);
        $this->assertResponseOk();

        $this->get('/flashcards/'.$flashcard->id);
        $this->assertResponseOk();

        $this->get('/tags/usuario/'.$this->userB->id);
        $this->assertResponseOk();
    }
}

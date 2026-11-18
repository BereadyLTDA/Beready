<?php

namespace Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Tests\ApiTestCase;

/**
 * AdminMiddleware: o escopo /admin exige role admin no banco (não na role do token),
 * e a action não chega a executar para quem não é admin.
 */
class AdminAccessTest extends ApiTestCase
{
    private const ADMIN_GET_URLS = ['/admin/planos', '/admin/roles', '/admin/users', '/admin/stats', '/admin/permissions'];

    private Model $admin;

    private Model $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->createUser('admin');
        $this->user = $this->createUser();
    }

    public function test_sem_token_retorna401(): void
    {
        $this->withBearer(null);

        foreach (self::ADMIN_GET_URLS as $url) {
            $this->get($url);
            $this->assertResponseCode(401, $url);
        }
    }

    public function test_usuario_comum_recebe403_sem_dados(): void
    {
        $this->actingAs($this->user);

        foreach (self::ADMIN_GET_URLS as $url) {
            $this->get($url);
            $this->assertResponseCode(403, $url);

            $body = $this->responseJson();
            $this->assertFalse($body['success'], $url);
            $this->assertArrayNotHasKey('data', $body, $url);
        }
    }

    public function test_role_admin_no_token_nao_basta_sem_role_admin_no_banco(): void
    {
        $this->actingAs($this->user, 'admin');

        $this->get('/admin/users');

        $this->assertResponseCode(403);
        $this->assertArrayNotHasKey('data', $this->responseJson());
    }

    public function test_usuario_comum_nao_executa_actions_de_escrita(): void
    {
        $this->actingAs($this->user);

        // Tentativa de se promover: a action não pode rodar
        $this->postJson('/admin/users/role', ['user_id' => $this->user->id, 'role' => 'admin']);
        $this->assertResponseCode(403);
        $this->assertSame('user', $this->fetch('Users', $this->user->id)->role);

        $this->postJson('/admin/planos', ['nome' => 'Plano Pirata', 'preco_mensal' => 0]);
        $this->assertResponseCode(403);
        $this->assertSame(0, $this->tabela('Planos')->where(['nome' => 'Plano Pirata'])->count());

        $this->postJson('/admin/roles', ['nome' => 'role-pirata']);
        $this->assertResponseCode(403);
        $this->assertSame(0, $this->tabela('Roles')->where(['nome' => 'role-pirata'])->count());

        $this->delete('/admin/planos/delete/'.self::PLANO_PREMIUM);
        $this->assertResponseCode(403);
        $this->assertNotNull($this->fetch('Planos', self::PLANO_PREMIUM));
    }

    public function test_admin_acessa_as_rotas_administrativas(): void
    {
        $this->actingAs($this->admin);

        $this->get('/admin/planos');
        $this->assertResponseOk();
        $this->assertCount(3, $this->responseJson()['data']);

        $this->get('/admin/roles');
        $this->assertResponseOk();
        $this->assertContains('admin', array_column($this->responseJson()['data'], 'nome'));

        $this->get('/admin/users');
        $this->assertResponseOk();
        $ids = array_column($this->responseJson()['data'], 'id');
        $this->assertSame([$this->admin->id, $this->user->id], $ids);
        $this->assertArrayNotHasKey('senha_hash', $this->responseJson()['data'][0]);
    }

    public function test_admin_altera_role_de_outro_usuario(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/admin/users/role', ['user_id' => $this->user->id, 'role' => 'admin']);

        $this->assertResponseOk();
        $this->assertSame('admin', $this->fetch('Users', $this->user->id)->role);
    }

    public function test_admin_nao_pode_rebaixar_a_si_mesmo(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/admin/users/role', ['user_id' => $this->admin->id, 'role' => 'user']);
        $this->assertResponseCode(403);
        $this->assertSame('admin', $this->fetch('Users', $this->admin->id)->role);

        $this->putJson('/users/update/'.$this->admin->id, ['role' => 'user']);
        $this->assertResponseCode(403);
        $this->assertSame('admin', $this->fetch('Users', $this->admin->id)->role);
    }

    public function test_admin_altera_role_e_status_pelo_update_de_usuario(): void
    {
        $this->actingAs($this->admin);

        $this->putJson('/users/update/'.$this->user->id, ['status' => 'bloqueado']);

        $this->assertResponseOk();
        $this->assertSame('bloqueado', $this->fetch('Users', $this->user->id)->status);
    }

    public function test_rebaixamento_vale_na_hora_mesmo_com_token_antigo(): void
    {
        $tokenAntigo = $this->tokensFor($this->admin)['access_token'];
        $this->tabela('Users')->where(['id' => $this->admin->id])->update(['role' => 'user']);

        $this->withBearer($tokenAntigo);
        $this->get('/admin/users');

        $this->assertResponseCode(403);
    }
}

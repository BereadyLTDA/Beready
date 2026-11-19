<?php

namespace Tests\Feature;

use App\Contracts\CloudinaryServiceInterface;
use App\Models\User;
use App\Services\SocialAuthService;
use ArrayObject;
use Tests\ApiTestCase;

/**
 * Foto do login social: o link do provedor é copiado para o Cloudinary, porque o do LinkedIn expira.
 */
class SocialAuthFotoTest extends ApiTestCase
{
    /** @var ArrayObject<int, string> */
    private ArrayObject $copiadas;

    protected function setUp(): void
    {
        parent::setUp();

        $copiadas = $this->copiadas = new ArrayObject;
        $this->mockService(CloudinaryServiceInterface::class, fn () => new class($copiadas) implements CloudinaryServiceInterface
        {
            /** @param ArrayObject<int, string> $copiadas */
            public function __construct(private ArrayObject $copiadas) {}

            public function uploadProfilePhoto(array $file): string
            {
                return 'https://res.cloudinary.test/upload.jpg';
            }

            public function copiarFotoRemota(string $url): string
            {
                if (str_contains($url, 'falha')) {
                    throw new \RuntimeException('indisponível');
                }
                $this->copiadas[] = $url;

                return 'https://res.cloudinary.test/copia.jpg';
            }
        });
    }

    private function servico(): SocialAuthService
    {
        return app(SocialAuthService::class);
    }

    public function test_usuario_novo_guarda_a_copia_da_foto(): void
    {
        $id = $this->servico()->findOrCreateUser([
            'email' => 'novo-social@beready.test',
            'name' => 'Novo Social',
            'picture' => 'https://media.licdn.com/dms/image/foto?e=1&t=x',
        ]);

        $this->assertSame('https://res.cloudinary.test/copia.jpg', User::findOrFail($id)->foto_perfil);
        $this->assertSame(['https://media.licdn.com/dms/image/foto?e=1&t=x'], $this->copiadas->getArrayCopy());
    }

    public function test_foto_antiga_do_linkedin_e_substituida(): void
    {
        $user = $this->createUser('user', ['email' => 'antigo-social@beready.test', 'foto_perfil' => 'https://media.licdn.com/expirada']);

        $this->servico()->findOrCreateUser([
            'email' => 'antigo-social@beready.test',
            'picture' => 'https://media.licdn.com/nova',
        ]);

        $this->assertSame('https://res.cloudinary.test/copia.jpg', User::findOrFail($user->id)->foto_perfil);
    }

    public function test_foto_escolhida_pelo_usuario_nao_e_trocada(): void
    {
        $user = $this->createUser('user', ['email' => 'manteve-social@beready.test', 'foto_perfil' => 'https://res.cloudinary.test/minha.jpg']);

        $this->servico()->findOrCreateUser([
            'email' => 'manteve-social@beready.test',
            'picture' => 'https://media.licdn.com/nova',
        ]);

        $this->assertSame('https://res.cloudinary.test/minha.jpg', User::findOrFail($user->id)->foto_perfil);
        $this->assertSame([], $this->copiadas->getArrayCopy());
    }

    public function test_falha_na_copia_mantem_o_link_original(): void
    {
        $id = $this->servico()->findOrCreateUser([
            'email' => 'falha-social@beready.test',
            'picture' => 'https://lh3.googleusercontent.test/falha.jpg',
        ]);

        $this->assertSame('https://lh3.googleusercontent.test/falha.jpg', User::findOrFail($id)->foto_perfil);
    }
}

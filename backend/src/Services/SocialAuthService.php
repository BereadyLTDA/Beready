<?php
declare(strict_types=1);

namespace App\Services;

use App\Mailer\UserMailer;
use Cake\Cache\Cache;
use Cake\Log\Log;
use Cake\ORM\TableRegistry;
use Ramsey\Uuid\Uuid;

class SocialAuthService
{
    private const CACHE_CONFIG = 'social_login';

    public function __construct(private AssinaturaService $assinaturaService)
    {
    }

    /**
     * Busca o usuário pelo e-mail do perfil social ou cria um novo. Retorna o id.
     */
    public function findOrCreateUser(array $userInfo): int
    {
        $usersTable = TableRegistry::getTableLocator()->get('Users');
        $user = $usersTable->find()->where(['email' => $userInfo['email']])->first();

        $name = $userInfo['name'] ?? $userInfo['given_name'] ?? $userInfo['first_name'] ?? 'Usuário';
        if (!empty($userInfo['given_name']) && !empty($userInfo['family_name'])) {
            $name = $userInfo['given_name'] . ' ' . $userInfo['family_name'];
        }
        $picture = $userInfo['picture'] ?? $userInfo['avatar'] ?? null;

        if ($user) {
            $updateData = [];
            if (empty($user->nome) || $user->nome === 'Usuário') {
                $updateData['nome'] = $name;
            }
            if (empty($user->foto_perfil) && $picture) {
                $updateData['foto_perfil'] = $picture;
            }
            if ($updateData) {
                $usersTable->saveOrFail($usersTable->patchEntity($user, $updateData));
            }

            return (int)$user->id;
        }

        $user = $usersTable->newEntity([
            'email' => $userInfo['email'],
            'nome' => $name,
            'nivel_ingles' => 'iniciante',
            'idioma_preferido' => 'pt-BR',
            'foto_perfil' => $picture,
        ]);
        $user->patch([
            'uuid' => Uuid::uuid4()->toString(),
            'status' => 'ativo',
            'role' => 'user',
        ], ['guard' => false]);
        $usersTable->saveOrFail($user);

        try {
            $this->assinaturaService->ativarPlanoGratuito((int)$user->id);
        } catch (\Exception $e) {
            Log::error('Erro ao atribuir plano gratuito (login social): ' . $e->getMessage());
        }

        try {
            (new UserMailer())->welcome($user);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar e-mail de boas-vindas (login social): ' . $e->getMessage());
        }

        return (int)$user->id;
    }

    /**
     * Gera um código aleatório de uso único (TTL do cache "social_login") ligado ao usuário.
     */
    public function createLoginCode(int $userId): string
    {
        $code = bin2hex(random_bytes(32));
        Cache::write($code, $userId, self::CACHE_CONFIG);

        return $code;
    }

    /**
     * Consome o código: devolve o id do usuário uma única vez, ou null se inválido/usado/expirado.
     */
    public function consumeLoginCode(string $code): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $code)) {
            return null;
        }

        $userId = Cache::read($code, self::CACHE_CONFIG);
        Cache::delete($code, self::CACHE_CONFIG);

        return $userId ? (int)$userId : null;
    }
}

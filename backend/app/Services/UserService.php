<?php

namespace App\Services;

use App\Exceptions\EmailAlreadyExistsException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\WeakPasswordException;
use App\Mail\BoasVindas;
use App\Mail\RecuperarSenha;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserService
{
    public const STATUS_ATIVO = 'ativo';

    public const EDITABLE_FIELDS = [
        'nome',
        'email',
        'telefone',
        'nivel_ingles',
        'idioma_preferido',
        'objetivos_aprendizado',
        'foto_perfil',
    ];

    /**
     * Campos do usuário devolvidos pela API (nunca a senha nem os tokens).
     */
    private const PUBLIC_FIELDS = [
        'id',
        'nome',
        'email',
        'role',
        'status',
        'telefone',
        'nivel_ingles',
        'idioma_preferido',
        'objetivos_aprendizado',
        'foto_perfil',
        'uuid',
        'criado_em',
        'atualizado_em',
        'ultimo_login',
    ];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function register(array $data): array
    {
        if (empty($data['nome']) || empty($data['email']) || empty($data['senha'])) {
            throw new \InvalidArgumentException('Nome, e-mail e senha são obrigatórios');
        }

        if (strlen((string) $data['senha']) < 6) {
            throw new WeakPasswordException('A senha deve ter pelo menos 6 caracteres');
        }

        if ($this->emailExists((string) $data['email'])) {
            throw new EmailAlreadyExistsException;
        }

        $user = new User(array_intersect_key($data, array_flip(self::EDITABLE_FIELDS)));
        // Campos protegidos (fora do $fillable): atribuídos só aqui
        $user->forceFill([
            'senha_hash' => password_hash((string) $data['senha'], PASSWORD_DEFAULT),
            'role' => 'user',
            'status' => self::STATUS_ATIVO,
        ])->saveOrFail();

        $this->enviarBoasVindas($user);

        return $user->toArray();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws \RuntimeException 401 (credenciais) ou 403 (conta inativa)
     */
    public function login(string $email, string $password): array
    {
        if ($email === '' || $password === '') {
            throw new \InvalidArgumentException('E-mail e senha são obrigatórios');
        }

        $user = $this->findOne(['email' => $email]);
        if (! $user || ! password_verify($password, (string) ($user['senha_hash'] ?? ''))) {
            throw new \RuntimeException('E-mail ou senha inválidos', 401);
        }

        if (! $this->isActive($user)) {
            throw new \RuntimeException('Conta inativa ou bloqueada', 403);
        }

        $this->registerLogin((int) $user['id']);
        unset($user['senha_hash']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $user
     */
    public function isActive(array $user): bool
    {
        return empty($user['status']) || $user['status'] === self::STATUS_ATIVO;
    }

    public function registerLogin(int $id): void
    {
        User::whereKey($id)->update(['ultimo_login' => now(), 'atualizado_em' => now()]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getUserById(int $id): array
    {
        $user = $this->findOne(['id' => $id]);
        if (! $user) {
            throw new UserNotFoundException;
        }
        unset($user['senha_hash']);

        return $user;
    }

    /**
     * Perfil resumido (consulta por UUID).
     *
     * @return array<string, mixed>
     */
    public function getUserByUuid(string $uuid): array
    {
        $user = User::query()
            ->select(['id', 'nome', 'email', 'role', 'status', 'foto_perfil', 'uuid'])
            ->where('uuid', $uuid)
            ->first();

        if (! $user) {
            throw new UserNotFoundException;
        }

        return $user->toArray();
    }

    /**
     * @param  array<string, mixed>  $data  campos editáveis (+ senha)
     * @param  array<string, mixed>  $protected  role/status, só quando quem edita é admin
     * @return array<string, mixed>
     */
    public function updateUser(int $id, array $data, array $protected = []): array
    {
        $user = User::find($id);
        if (! $user) {
            throw new UserNotFoundException;
        }

        if (isset($data['email']) && $this->emailExists((string) $data['email'], $id)) {
            throw new EmailAlreadyExistsException('Este e-mail já está em uso');
        }

        if (isset($data['senha']) && $data['senha'] !== '') {
            if (strlen((string) $data['senha']) < 6) {
                throw new WeakPasswordException('A senha deve ter pelo menos 6 caracteres');
            }
            $protected['senha_hash'] = password_hash((string) $data['senha'], PASSWORD_DEFAULT);
        }

        $user->fill(array_intersect_key($data, array_flip(self::EDITABLE_FIELDS)));
        if ($protected) {
            $user->forceFill($protected);
        }
        $user->saveOrFail();

        return $user->toArray();
    }

    public function deleteUser(int $id): bool
    {
        $user = User::find($id);
        if (! $user) {
            throw new UserNotFoundException;
        }

        return (bool) $user->delete();
    }

    /**
     * Gera o token de recuperação (válido por 1 hora; no banco fica só o hash) e envia o e-mail.
     * E-mail desconhecido não gera erro, para não revelar quais e-mails têm conta.
     */
    public function forgotPassword(string $email): void
    {
        $user = User::where('email', $email)->first();
        if (! $user) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $user->forceFill([
            'reset_token' => hash('sha256', $token),
            'reset_token_expires' => now()->addHour(),
        ])->save();

        try {
            Mail::to($user->email)->send(new RecuperarSenha($user->nome, $token));
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar e-mail de recuperação de senha: '.$e->getMessage());
        }
    }

    public function resetPassword(string $token, string $newPassword): void
    {
        $user = User::where('reset_token', hash('sha256', $token))
            ->where('reset_token_expires', '>', now())
            ->first();

        if (! $user) {
            throw new InvalidTokenException('Token inválido ou expirado');
        }

        if (strlen($newPassword) < 6) {
            throw new WeakPasswordException('A senha deve ter pelo menos 6 caracteres');
        }

        $user->forceFill([
            'senha_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
            'reset_token' => null,
            'reset_token_expires' => null,
        ])->saveOrFail();
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        return User::where('email', $email)
            ->when($excludeId, fn ($q) => $q->whereKeyNot($excludeId))
            ->exists();
    }

    /**
     * Usuário com os campos públicos + senha_hash (para conferir a senha); role vazia vira "user".
     *
     * @param  array<string, mixed>  $conditions
     * @return array<string, mixed>|null
     */
    private function findOne(array $conditions): ?array
    {
        $user = User::query()->select([...self::PUBLIC_FIELDS, 'senha_hash'])->where($conditions)->first();
        if (! $user) {
            return null;
        }

        $data = $user->toArray();
        $data['senha_hash'] = $user->senha_hash;
        if (empty($data['role'])) {
            $data['role'] = 'user';
        }

        return $data;
    }

    private function enviarBoasVindas(User $user): void
    {
        try {
            Mail::to($user->email)->send(new BoasVindas((string) $user->nome));
        } catch (\Throwable $e) {
            // Não interrompe o registro se o e-mail falhar
            Log::error('Erro ao enviar e-mail de boas-vindas: '.$e->getMessage());
        }
    }
}

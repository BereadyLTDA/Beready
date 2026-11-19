<?php

namespace App\Services;

use App\Exceptions\EmailAlreadyExistsException;
use App\Exceptions\InvalidTokenException;
use App\Exceptions\UserNotFoundException;
use App\Exceptions\WeakPasswordException;
use App\Mail\BoasVindas;
use App\Mail\RecuperarSenha;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\DB;
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

        $data['email'] = $this->validarEmail((string) $data['email']);

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

        $user = $this->findPorEmail($email);
        // Sem conta, compara com um hash qualquer: o tempo de resposta não revela quais e-mails existem
        $hash = (string) ($user['senha_hash'] ?? self::hashFicticio());
        if (! password_verify($password, $hash) || ! $user) {
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
    public function updateUser(int $id, array $data, array $protected = [], bool $exigirSenhaAtual = false): array
    {
        $user = User::find($id);
        if (! $user) {
            throw new UserNotFoundException;
        }

        if (isset($data['email'])) {
            $data['email'] = $this->validarEmail((string) $data['email']);
        }
        $trocaEmail = isset($data['email']) && $data['email'] !== mb_strtolower((string) $user->getAttribute('email'));
        $trocaSenha = isset($data['senha']) && $data['senha'] !== '';

        // Quem edita a própria conta confirma a senha atual para trocar e-mail ou senha: um token
        // roubado não basta para tomar a conta. Conta só com login social (sem senha) não precisa.
        if ($exigirSenhaAtual && ($trocaEmail || $trocaSenha) && ! empty($user->senha_hash)) {
            if (! password_verify((string) ($data['senha_atual'] ?? ''), (string) $user->senha_hash)) {
                throw new \InvalidArgumentException('Senha atual incorreta');
            }
        }

        if (isset($data['foto_perfil']) && $data['foto_perfil'] !== '' && ! str_starts_with((string) $data['foto_perfil'], 'https://')) {
            throw new \InvalidArgumentException('A foto de perfil deve ser um endereço https');
        }

        if ($trocaEmail && $this->emailExists((string) $data['email'], $id)) {
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

        return DB::transaction(function () use ($user) {
            // Tags do usuário saem junto: com ON DELETE SET NULL virariam "sem dono" e colidiriam
            // com o índice único de nome das tags sem dono quando outro usuário excluísse a conta
            Tag::where('criado_por', $user->id)->delete();

            return (bool) $user->delete();
        });
    }

    /**
     * Gera o token de recuperação (válido por 1 hora; no banco fica só o hash) e envia o e-mail.
     * E-mail desconhecido não gera erro, para não revelar quais e-mails têm conta.
     */
    public function forgotPassword(string $email): void
    {
        $user = User::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();
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

    /**
     * @return int id do usuário cuja senha foi redefinida
     */
    public function resetPassword(string $token, string $newPassword): int
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

        return (int) $user->id;
    }

    public function emailExists(string $email, ?int $excludeId = null): bool
    {
        return User::whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])
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

    /**
     * Hash de uma senha aleatória, gerado uma vez por processo, só para igualar o tempo do login
     * quando o e-mail não tem conta.
     */
    private static function hashFicticio(): string
    {
        static $hash = null;

        return $hash ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }

    /**
     * E-mail em minúsculas e com formato válido (o banco compara com lower(email)).
     */
    private function validarEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('E-mail inválido');
        }

        return $email;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findPorEmail(string $email): ?array
    {
        $user = User::query()->select(['id'])->whereRaw('lower(email) = ?', [mb_strtolower(trim($email))])->first();

        return $user ? $this->findOne(['id' => $user->id]) : null;
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

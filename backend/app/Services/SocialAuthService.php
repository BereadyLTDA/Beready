<?php

namespace App\Services;

use App\Contracts\CloudinaryServiceInterface;
use App\Mail\BoasVindas;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SocialAuthService
{
    /**
     * Validade do código de uso único entregue ao frontend após o login social.
     */
    private const CODIGO_TTL_SEGUNDOS = 60;

    private const PREFIXO_CACHE = 'social_login_';

    public function __construct(
        private AssinaturaService $assinaturaService,
        private CloudinaryServiceInterface $cloudinary,
        private JwtService $jwtService,
    ) {}

    /**
     * Busca o usuário pelo e-mail do perfil social ou cria um novo. Retorna o id.
     *
     * @param  array<string, mixed>  $userInfo  perfil devolvido pelo provedor
     */
    public function findOrCreateUser(array $userInfo): int
    {
        $userInfo['email'] = mb_strtolower(trim((string) $userInfo['email']));
        $user = User::whereRaw('lower(email) = ?', [$userInfo['email']])->first();

        $name = $userInfo['name'] ?? $userInfo['given_name'] ?? $userInfo['first_name'] ?? 'Usuário';
        if (! empty($userInfo['given_name']) && ! empty($userInfo['family_name'])) {
            $name = $userInfo['given_name'].' '.$userInfo['family_name'];
        }
        $picture = $userInfo['picture'] ?? $userInfo['avatar'] ?? null;

        if ($user) {
            // O provedor provou que a pessoa é dona do e-mail. Se a conta nunca foi confirmada, quem a
            // criou com senha pode não ser o dono: a senha é descartada e as sessões antigas caem.
            if (empty($user->getAttribute('email_verificado_em'))) {
                $user->forceFill([
                    'senha_hash' => null,
                    'email_verificado_em' => now(),
                    'verificacao_token' => null,
                    'verificacao_token_expira' => null,
                ]);
                $this->jwtService->revogarTodos((int) $user->id);
            }
            if (empty($user->nome) || $user->nome === 'Usuário') {
                $user->nome = $name;
            }
            if ($picture && (empty($user->foto_perfil) || $this->fotoExpira((string) $user->foto_perfil))) {
                $user->foto_perfil = $this->fotoPermanente($picture);
            }
            if ($user->isDirty()) {
                $user->saveOrFail();
            }

            return (int) $user->id;
        }

        $user = new User([
            'email' => $userInfo['email'],
            'nome' => $name,
            'nivel_ingles' => 'iniciante',
            'idioma_preferido' => 'pt-BR',
            'foto_perfil' => $picture ? $this->fotoPermanente($picture) : null,
        ]);
        $user->forceFill(['status' => 'ativo', 'role' => 'user', 'email_verificado_em' => now()])->saveOrFail();

        try {
            $this->assinaturaService->ativarPlanoGratuito((int) $user->id);
        } catch (\Throwable $e) {
            Log::error('Erro ao atribuir plano gratuito (login social): '.$e->getMessage());
        }

        try {
            Mail::to($user->email)->send(new BoasVindas((string) $user->nome));
        } catch (\Throwable $e) {
            Log::error('Erro ao enviar e-mail de boas-vindas (login social): '.$e->getMessage());
        }

        return (int) $user->id;
    }

    /**
     * Copia a foto do provedor para o Cloudinary. O LinkedIn entrega links assinados que expiram
     * (o navegador passa a receber 403), então guardar o link original quebra a foto depois.
     * Se a cópia falhar, fica o link original.
     */
    private function fotoPermanente(string $url): string
    {
        try {
            return $this->cloudinary->copiarFotoRemota($url);
        } catch (\Throwable $e) {
            Log::warning('Não foi possível copiar a foto do login social: '.$e->getMessage());

            return $url;
        }
    }

    /**
     * Fotos gravadas antes desta correção com o link temporário do LinkedIn.
     */
    private function fotoExpira(string $url): bool
    {
        return str_contains($url, 'media.licdn.com');
    }

    /**
     * Gera um código aleatório de uso único (válido por 60 s) ligado ao usuário.
     */
    public function createLoginCode(int $userId): string
    {
        $code = bin2hex(random_bytes(32));
        Cache::put(self::PREFIXO_CACHE.$code, $userId, self::CODIGO_TTL_SEGUNDOS);

        return $code;
    }

    /**
     * Consome o código: devolve o id do usuário uma única vez, ou null se inválido/usado/expirado.
     */
    public function consumeLoginCode(string $code): ?int
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $code)) {
            return null;
        }

        // pull() lê e apaga: o mesmo código não serve duas vezes
        $userId = Cache::pull(self::PREFIXO_CACHE.$code);

        return $userId ? (int) $userId : null;
    }
}

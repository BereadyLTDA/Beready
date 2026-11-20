<?php

namespace App\Services;

use App\Mail\LembreteEstudo;
use App\Models\Flashcard;
use App\Models\PreferenciaUsuario;
use App\Models\RespostaUsuario;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Lembrete por e-mail para quem tem cards vencidos e parou de estudar. Roda pelo scheduler
 * (php artisan beready:lembretes, todo dia às 18h).
 */
class LembreteService
{
    /** Dias sem revisar até receber o lembrete */
    public const DIAS_INATIVO = 2;

    /** Intervalo mínimo entre dois lembretes para o mesmo aluno */
    public const DIAS_ENTRE_LEMBRETES = 3;

    /**
     * @return int quantos lembretes foram enviados
     */
    public function enviar(): int
    {
        $limiteInatividade = now()->subDays(self::DIAS_INATIVO);
        $limiteLembrete = now()->subDays(self::DIAS_ENTRE_LEMBRETES);

        $candidatos = User::query()
            ->where('status', 'ativo')
            ->whereNotNull('email_verificado_em')
            ->where(fn ($q) => $q->whereNull('ultimo_lembrete_em')->orWhere('ultimo_lembrete_em', '<', $limiteLembrete))
            // Quem desligou as notificações não recebe
            ->whereNotIn('id', PreferenciaUsuario::where('notificacoes_ativas', false)->select('usuario_id'))
            // Revisou algo recentemente: não precisa de lembrete
            ->whereNotIn('id', RespostaUsuario::where('criado_em', '>=', $limiteInatividade)->select('usuario_id'))
            ->whereIn('id', Flashcard::where('proxima_revisao', '<=', now())->select('usuario_id'))
            ->get();

        $enviados = 0;
        foreach ($candidatos as $user) {
            $vencidos = Flashcard::where('usuario_id', $user->id)->where('proxima_revisao', '<=', now())->count();
            try {
                Mail::to($user->getAttribute('email'))->send(new LembreteEstudo($user->getAttribute('nome'), $vencidos));
                $user->forceFill(['ultimo_lembrete_em' => now()])->save();
                $enviados++;
            } catch (\Throwable $e) {
                Log::error("Erro ao enviar lembrete para o usuário {$user->id}: ".$e->getMessage());
            }
        }

        return $enviados;
    }
}

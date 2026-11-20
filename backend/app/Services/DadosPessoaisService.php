<?php

namespace App\Services;

use App\Models\Assinatura;
use App\Models\ConquistaUsuario;
use App\Models\Flashcard;
use App\Models\FraseSemelhante;
use App\Models\ImagemGerada;
use App\Models\Nivelamento;
use App\Models\PreferenciaUsuario;
use App\Models\ProgressoUsuario;
use App\Models\Prompt;
use App\Models\PronunciaTentativa;
use App\Models\Quiz;
use App\Models\QuizQuestao;
use App\Models\RespostaUsuario;
use App\Models\Tag;
use App\Models\Traducao;
use App\Models\User;

/**
 * LGPD (direito de acesso e portabilidade): tudo o que o BeReady guarda sobre o aluno, num JSON.
 * Não inclui segredos (senha, tokens) nem identificadores internos de pagamento.
 */
class DadosPessoaisService
{
    /**
     * @return array<string, mixed>
     */
    public function exportar(int $usuarioId): array
    {
        $user = User::findOrFail($usuarioId);
        $promptIds = Prompt::where('usuario_id', $usuarioId)->pluck('id');
        $quizIds = Quiz::where('usuario_id', $usuarioId)->pluck('id');

        return [
            'gerado_em' => now()->toAtomString(),
            'termos' => [
                'versao_aceita' => $user->getAttribute('termos_versao'),
                'aceitos_em' => $user->getAttribute('termos_aceitos_em')?->toAtomString(),
            ],
            'perfil' => $user->makeHidden([])->toArray(),
            'preferencias' => PreferenciaUsuario::where('usuario_id', $usuarioId)->first()?->toArray(),
            'progresso' => ProgressoUsuario::where('usuario_id', $usuarioId)->first()?->toArray(),
            'flashcards' => Flashcard::where('usuario_id', $usuarioId)->orderBy('id')->get()->toArray(),
            'tags' => Tag::where('criado_por', $usuarioId)->orderBy('id')->get()->toArray(),
            'quizes' => Quiz::whereIn('id', $quizIds)->orderBy('id')->get()->toArray(),
            'quiz_questoes' => QuizQuestao::whereIn('quiz_id', $quizIds)->with('alternativas')->orderBy('id')->get()->toArray(),
            'prompts' => Prompt::whereIn('id', $promptIds)->orderBy('id')->get()->toArray(),
            'traducoes' => Traducao::whereIn('prompt_id', $promptIds)->orderBy('id')->get()->toArray(),
            'frases_semelhantes' => FraseSemelhante::whereIn('prompt_id', $promptIds)->orderBy('id')->get()->toArray(),
            'imagens_geradas' => ImagemGerada::whereIn('prompt_id', $promptIds)->orderBy('id')->get()->toArray(),
            'respostas' => RespostaUsuario::where('usuario_id', $usuarioId)->orderBy('id')->get()->toArray(),
            'pronuncia_tentativas' => PronunciaTentativa::where('usuario_id', $usuarioId)->orderBy('id')->get()->toArray(),
            'conquistas' => ConquistaUsuario::where('usuario_id', $usuarioId)->orderBy('id')->get()->toArray(),
            'nivelamentos' => Nivelamento::where('usuario_id', $usuarioId)->orderBy('id')->get()->toArray(),
            // Sem payment_id e stripe_subscription_id: são identificadores internos do Stripe
            'assinaturas' => Assinatura::where('usuario_id', $usuarioId)->orderBy('id')->get()
                ->map(fn (Assinatura $a) => collect($a->toArray())->except(['payment_id', 'stripe_subscription_id'])->all())
                ->all(),
        ];
    }
}

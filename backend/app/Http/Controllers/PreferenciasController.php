<?php

namespace App\Http\Controllers;

use App\Models\PreferenciaUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PreferenciasController extends Controller
{
    /**
     * Preferências de quem nunca salvou nenhuma.
     */
    private const PADRAO = [
        'tema' => 'claro',
        'modo_daltonico' => false,
        'notificacoes_ativas' => true,
        'som_ativo' => true,
        'traducao_automatica' => true,
        'preferencia_dificuldade' => 'adaptativo',
        'meta_diaria_minutos' => 30,
    ];

    private const CAMPOS = [
        'tema', 'modo_daltonico', 'notificacoes_ativas', 'som_ativo',
        'traducao_automatica', 'preferencia_dificuldade', 'meta_diaria_minutos',
    ];

    // GET /preferencias/usuario/{usuarioId}
    public function getByUsuario(string $usuarioId): JsonResponse
    {
        try {
            if (! $this->canAccessUser((int) $usuarioId)) {
                return $this->jsonError('Acesso negado', 403);
            }

            $preferencias = PreferenciaUsuario::where('usuario_id', (int) $usuarioId)->first();

            return $this->jsonSuccess($preferencias?->toArray() ?? ['usuario_id' => (int) $usuarioId] + self::PADRAO);
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao carregar preferências');
        }
    }

    // POST /preferencias — sempre do usuário autenticado (usuario_id do corpo é ignorado)
    public function save(Request $request): JsonResponse
    {
        try {
            $usuarioId = $this->currentUserId();
            $data = array_intersect_key($this->requestData($request), array_flip(self::CAMPOS));

            $preferencias = PreferenciaUsuario::firstOrNew(['usuario_id' => $usuarioId]);
            $preferencias->fill($data + ['usuario_id' => $usuarioId])->saveOrFail();

            return $this->jsonSuccess($preferencias->toArray(), 'Preferências salvas com sucesso');
        } catch (\Exception $e) {
            return $this->errorResponse($e, 'Erro ao salvar preferências');
        }
    }
}

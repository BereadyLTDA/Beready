<?php

namespace App\Http\Controllers;

use App\Models\Permissao;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Acesso restrito a admins pelo middleware `admin` (grupo /admin).
 */
class AdminPermissionsController extends Controller
{
    public function index(): JsonResponse
    {
        try {
            $permissoes = Permissao::where('is_ativo', true)->orderBy('recurso')->orderBy('acao')->get();

            return $this->jsonSuccess($permissoes);
        } catch (\Exception $e) {
            Log::error('Erro ao listar permissões (admin): '.$e->getMessage());

            return $this->jsonError('Erro ao listar permissões', 500);
        }
    }
}

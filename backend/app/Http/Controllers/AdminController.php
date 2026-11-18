<?php

namespace App\Http\Controllers;

use App\Models\Flashcard;
use App\Models\ImagemGerada;
use App\Models\Prompt;
use App\Models\Quiz;
use App\Models\Tag;
use App\Models\Traducao;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Acesso restrito a admins pelo middleware `admin` (grupo /admin).
 */
class AdminController extends Controller
{
    // GET /admin/users - Lista todos os usuários
    public function users(): JsonResponse
    {
        try {
            $users = User::query()
                ->select(['id', 'nome', 'email', 'role', 'status', 'criado_em', 'ultimo_login'])
                ->orderBy('id')
                ->get();

            return $this->jsonSuccess($users);
        } catch (\Exception $e) {
            Log::error('Erro ao listar usuários (admin): '.$e->getMessage());

            return $this->jsonError('Erro ao listar usuários', 500);
        }
    }

    // POST /admin/users/role  { user_id, role }
    public function updateRole(Request $request): JsonResponse
    {
        $data = $this->requestData($request);
        $userId = (int) ($data['user_id'] ?? 0);
        $newRole = $data['role'] ?? null;

        if (! $userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        if (! in_array($newRole, ['user', 'admin'], true)) {
            return $this->jsonError('Role inválida. Use "user" ou "admin"', 400);
        }

        if ($userId === $this->currentUserId() && $newRole !== 'admin') {
            return $this->jsonError('Você não pode rebaixar seu próprio nível de acesso', 403);
        }

        $user = User::find($userId);
        if (! $user) {
            return $this->jsonError('Usuário não encontrado', 404);
        }

        // role fica fora do $fillable: só o admin altera, e só por aqui (ou pelo update de usuário)
        $user->role = $newRole;
        if (! $user->save()) {
            return $this->jsonError('Erro ao atualizar role', 500);
        }

        return $this->jsonSuccess([
            'id' => $user->id,
            'nome' => $user->nome,
            'role' => $user->role,
        ], 'Role atualizada com sucesso');
    }

    // GET /admin/stats
    public function stats(): JsonResponse
    {
        try {
            return $this->jsonSuccess([
                'total_users' => User::count(),
                'total_flashcards' => Flashcard::count(),
                'total_quizes' => Quiz::count(),
                'total_prompts' => Prompt::count(),
                'total_tags' => Tag::count(),
                'total_traducoes' => Traducao::count(),
                'total_imagens' => ImagemGerada::count(),
                'admin_count' => User::where('role', 'admin')->count(),
                'user_count' => User::where('role', 'user')->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar estatísticas (admin): '.$e->getMessage());

            return $this->jsonError('Erro ao carregar estatísticas', 500);
        }
    }
}

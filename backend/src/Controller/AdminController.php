<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;
use App\Services\JwtService;

class AdminController extends AppController
{
    private $usersTable;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->usersTable = TableRegistry::getTableLocator()->get('Users');
        
        // Verifica a role manualmente
        $authHeader = $this->request->getHeaderLine('Authorization');
        $role = 'user';
        
        if (preg_match('/Bearer\s+(.+)/', $authHeader, $matches)) {
            $token = $matches[1];
            $jwtService = new JwtService();
            $payload = $jwtService->validateToken($token);
            $role = $payload['role'] ?? 'user';
        }
        
        if ($role !== 'admin') {
            $this->response = $this->response->withStatus(403);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Acesso negado. Área administrativa.'
            ]));
            $this->response = $this->response->withType('application/json');
            
            // IMPEDE que o CakePHP continue processando
            $this->autoRender = false;
            $this->response = $this->response->send();
            exit; // Para a execução completamente
        }
    }
    
    // GET /admin/users - Lista todos os usuários
    public function users()
    {
        try {
            $users = $this->usersTable->find()
                ->select(['id', 'nome', 'email', 'role', 'status', 'criado_em', 'ultimo_login'])
                ->orderBy(['id' => 'ASC'])
                ->all();
            
            return $this->jsonSuccess($users->toArray());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // PUT /admin/users/{id}/role
    public function updateRole($id = null)
    {
        error_log("=== UPDATE ROLE ===");
        
        $data = $this->getRequestData();
        error_log("Dados recebidos: " . print_r($data, true));
        
        // ✅ Pega o ID dos dados (não da URL)
        $userId = $data['user_id'] ?? null;
        $newRole = $data['role'] ?? null;
        
        error_log("User ID: " . ($userId ?? 'NULL'));
        error_log("New Role: " . ($newRole ?? 'NULL'));
        
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        
        if (!in_array($newRole, ['user', 'admin'])) {
            return $this->jsonError('Role inválida. Use "user" ou "admin"', 400);
        }
        
        try {
            $user = $this->usersTable->get($userId);
            
            if (!$user) {
                error_log("Usuário ID {$userId} NÃO encontrado");
                return $this->jsonError('Usuário não encontrado', 404);
            }
            
            $currentUserId = $this->request->getAttribute('user_id');
            
            if ($user->id == $currentUserId && $newRole !== 'admin') {
                return $this->jsonError('Você não pode rebaixar seu próprio nível de acesso', 403);
            }
            
            $oldRole = $user->role;
            $user->role = $newRole;
            
            if ($this->usersTable->save($user)) {
                error_log("Role atualizada: {$oldRole} -> {$newRole} para usuário {$userId}");
                return $this->jsonSuccess([
                    'id' => $user->id,
                    'nome' => $user->nome,
                    'role' => $user->role
                ], 'Role atualizada com sucesso');
            }
            
            return $this->jsonError('Erro ao atualizar role', 500);
        } catch (\Exception $e) {
            error_log("EXCEÇÃO: " . $e->getMessage());
            return $this->jsonError('Usuário não encontrado', 404);
        }
    }
    
    // GET /admin/stats
    public function stats()
    {
        try {
            $stats = [];
            $stats['total_users'] = $this->usersTable->find()->count();
            
            $flashcardsTable = TableRegistry::getTableLocator()->get('Flashcards');
            $stats['total_flashcards'] = $flashcardsTable->find()->count();
            
            $quizesTable = TableRegistry::getTableLocator()->get('Quizes');
            $stats['total_quizes'] = $quizesTable->find()->count();
            
            $promptsTable = TableRegistry::getTableLocator()->get('Prompts');
            $stats['total_prompts'] = $promptsTable->find()->count();
            
            $tagsTable = TableRegistry::getTableLocator()->get('Tags');
            $stats['total_tags'] = $tagsTable->find()->count();
            
            $traducoesTable = TableRegistry::getTableLocator()->get('Traducoes');
            $stats['total_traducoes'] = $traducoesTable->find()->count();
            
            $imagensTable = TableRegistry::getTableLocator()->get('ImagensGeradas');
            $stats['total_imagens'] = $imagensTable->find()->count();
            
            $stats['admin_count'] = $this->usersTable->find()->where(['role' => 'admin'])->count();
            $stats['user_count'] = $this->usersTable->find()->where(['role' => 'user'])->count();
            
            return $this->jsonSuccess($stats);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    private function getRequestData(): array
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        if (!$data) {
            $data = $this->request->getData();
        }
        return $data;
    }
}
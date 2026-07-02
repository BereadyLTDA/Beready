<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class PreferenciasController extends AppController
{
    private $table;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('PreferenciasUsuario');
    }
    
    // GET /preferencias/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId') ?? $this->request->getQuery('usuarioId');
        
        error_log("=== GET PREFERENCIAS ===");
        error_log("Final userId: " . $userId);
        
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        
        try {
            $preferencia = $this->table->find()
                ->where(['usuario_id' => $userId])
                ->first();
            
            if (!$preferencia) {
                return $this->jsonSuccess([
                    'usuario_id' => (int)$userId,
                    'tema' => 'claro',
                    'modo_daltonico' => false,
                    'notificacoes_ativas' => true,
                    'som_ativo' => true,
                    'traducao_automatica' => true,
                    'preferencia_dificuldade' => 'adaptativo',
                    'meta_diaria_minutos' => 30,
                ]);
            }
            
            return $this->jsonSuccess($preferencia);
        } catch (\Exception $e) {
            error_log("ERRO getByUsuario: " . $e->getMessage());
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // POST /preferencias
    public function save()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        error_log("=== SAVE PREFERENCIAS ===");
        error_log("Dados recebidos: " . print_r($data, true));
        
        if (empty($data['usuario_id'])) {
            return $this->jsonError('ID do usuário é obrigatório', 400);
        }
        
        try {
            $existing = $this->table->find()
                ->where(['usuario_id' => $data['usuario_id']])
                ->first();
            
            // Campos permitidos
            $allowedFields = ['tema', 'modo_daltonico', 'notificacoes_ativas', 'som_ativo', 'traducao_automatica', 'preferencia_dificuldade', 'meta_diaria_minutos'];
            $saveData = array_intersect_key($data, array_flip($allowedFields));
            $saveData['usuario_id'] = $data['usuario_id'];
            
            error_log("Dados para salvar: " . print_r($saveData, true));
            
            if ($existing) {
                $entity = $this->table->patchEntity($existing, $saveData);
            } else {
                $entity = $this->table->newEntity($saveData);
            }
            
            if ($this->table->save($entity)) {
                error_log("Preferências salvas com sucesso ID: " . $entity->id);
                return $this->jsonSuccess($entity, 'Preferências salvas com sucesso');
            }
            
            error_log("Erro ao salvar: " . print_r($entity->getErrors(), true));
            return $this->jsonError('Erro ao salvar preferências', 422, $entity->getErrors());
        } catch (\Exception $e) {
            error_log("EXCEÇÃO ao salvar preferências: " . $e->getMessage());
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
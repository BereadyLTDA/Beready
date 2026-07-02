<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class PromptsController extends AppController
{
    private $table;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('Prompts');
    }
    
    // GET /prompts/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId') ?? $this->request->getQuery('usuarioId');
        
        error_log("=== GET BY USUARIO ===");
        error_log("usuarioId param: " . ($usuarioId ?? 'null'));
        error_log("usuarioId from request: " . ($this->request->getParam('usuarioId') ?? 'null'));
        error_log("Final userId: " . $userId);
        
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        
        try {
            $prompts = $this->table->find()
                ->where(['usuario_id' => $userId])
                ->orderBy(['criado_em' => 'DESC'])
                ->all();
            
            return $this->jsonSuccess($prompts->toArray());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    // GET /prompts/view/{id}
    public function view($id = null)
    {
        $promptId = $id ?? $this->request->getParam('id') ?? $this->request->getQuery('id');
        
        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }
        
        try {
            $prompt = $this->table->get($promptId);
            return $this->jsonSuccess($prompt);
        } catch (\Exception $e) {
            return $this->jsonError('Prompt não encontrado', 404);
        }
    }
    
    // POST /prompts
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        if (empty($data['usuario_id'])) {
            return $this->jsonError('ID do usuário é obrigatório', 400);
        }
        
        if (empty($data['texto_original'])) {
            return $this->jsonError('Texto original é obrigatório', 400);
        }
        
        try {
            $prompt = $this->table->newEntity($data);
            
            if ($this->table->save($prompt)) {
                return $this->jsonSuccess($prompt, 'Prompt criado com sucesso', 201);
            }
            
            return $this->jsonError('Erro ao criar prompt', 422, $prompt->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    // PUT /prompts/edit/{id}
    public function edit($id = null)
    {
        $promptId = $id ?? $this->request->getParam('id');
        
        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $prompt = $this->table->get($promptId);
            $prompt = $this->table->patchEntity($prompt, $data);
            
            if ($this->table->save($prompt)) {
                return $this->jsonSuccess($prompt, 'Prompt atualizado com sucesso');
            }
            
            return $this->jsonError('Erro ao atualizar prompt', 422, $prompt->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    // DELETE /prompts/delete/{id}
    public function delete($id = null)
    {
        $promptId = $id ?? $this->request->getParam('id');
        
        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }
        
        try {
            $prompt = $this->table->get($promptId);
            
            if ($this->table->delete($prompt)) {
                return $this->jsonSuccess(null, 'Prompt excluído com sucesso');
            }
            
            return $this->jsonError('Erro ao excluir prompt', 500);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
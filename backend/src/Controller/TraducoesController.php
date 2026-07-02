<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class TraducoesController extends AppController
{
    private $table;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('Traducoes');
    }
    
    // GET /traducoes/prompt/{promptId}
    public function getByPrompt($promptId = null)
    {
        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }
        
        try {
            $traducoes = $this->table->find()
                ->where(['prompt_id' => $promptId])
                ->orderBy(['criado_em' => 'DESC'])
                ->all();
            
            return $this->jsonSuccess($traducoes->toArray());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // GET /traducoes/view/{id}
    public function view($id = null)
    {
        $traducaoId = $id ?? $this->request->getParam('id');
        
        if (!$traducaoId) {
            return $this->jsonError('ID da tradução não informado', 400);
        }
        
        try {
            $traducao = $this->table->get($traducaoId);
            return $this->jsonSuccess($traducao);
        } catch (\Exception $e) {
            return $this->jsonError('Tradução não encontrada', 404);
        }
    }
    
    // POST /traducoes
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        if (empty($data['prompt_id'])) {
            return $this->jsonError('ID do prompt é obrigatório', 400);
        }
        
        if (empty($data['texto_traduzido'])) {
            return $this->jsonError('Texto traduzido é obrigatório', 400);
        }
        
        try {
            if (isset($data['traducoes_alternativas']) && is_array($data['traducoes_alternativas'])) {
                $data['traducoes_alternativas'] = json_encode($data['traducoes_alternativas']);
            }
            
            $traducao = $this->table->newEntity($data);
            
            if ($this->table->save($traducao)) {
                return $this->jsonSuccess($traducao, 'Tradução criada com sucesso', 201);
            }
            
            return $this->jsonError('Erro ao criar tradução', 422, $traducao->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // PUT /traducoes/edit/{id}
    public function edit($id = null)
    {
        $traducaoId = $id ?? $this->request->getParam('id');
        
        if (!$traducaoId) {
            return $this->jsonError('ID da tradução não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $traducao = $this->table->get($traducaoId);
            
            if (isset($data['traducoes_alternativas']) && is_array($data['traducoes_alternativas'])) {
                $data['traducoes_alternativas'] = json_encode($data['traducoes_alternativas']);
            }
            
            $traducao = $this->table->patchEntity($traducao, $data);
            
            if ($this->table->save($traducao)) {
                return $this->jsonSuccess($traducao, 'Tradução atualizada com sucesso');
            }
            
            return $this->jsonError('Erro ao atualizar tradução', 422, $traducao->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // DELETE /traducoes/delete/{id}
    public function delete($id = null)
    {
        $traducaoId = $id ?? $this->request->getParam('id');
        
        if (!$traducaoId) {
            return $this->jsonError('ID da tradução não informado', 400);
        }
        
        try {
            $traducao = $this->table->get($traducaoId);
            
            if ($this->table->delete($traducao)) {
                return $this->jsonSuccess(null, 'Tradução excluída com sucesso');
            }
            
            return $this->jsonError('Erro ao excluir tradução', 500);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
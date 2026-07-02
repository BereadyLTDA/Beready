<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class FrasesSemelhantesController extends AppController
{
    private $table;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('FrasesSemelhantes');
    }
    
    // GET /frases/prompt/{promptId}
    public function getByPrompt($promptId = null)
    {
        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }
        
        try {
            $frases = $this->table->find()
                ->where(['prompt_id' => $promptId])
                ->orderBy(['pontuacao_semelhante' => 'DESC'])
                ->all();
            
            return $this->jsonSuccess($frases->toArray());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // GET /frases/view/{id}
    public function view($id = null)
    {
        $fraseId = $id ?? $this->request->getParam('id');
        
        if (!$fraseId) {
            return $this->jsonError('ID da frase não informado', 400);
        }
        
        try {
            $frase = $this->table->get($fraseId);
            return $this->jsonSuccess($frase);
        } catch (\Exception $e) {
            return $this->jsonError('Frase não encontrada', 404);
        }
    }
    
    // POST /frases
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        if (empty($data['prompt_id'])) {
            return $this->jsonError('ID do prompt é obrigatório', 400);
        }
        
        if (empty($data['frase_semelhante'])) {
            return $this->jsonError('Frase semelhante é obrigatória', 400);
        }
        
        try {
            $frase = $this->table->newEntity($data);
            
            if ($this->table->save($frase)) {
                return $this->jsonSuccess($frase, 'Frase criada com sucesso', 201);
            }
            
            return $this->jsonError('Erro ao criar frase', 422, $frase->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // PUT /frases/edit/{id}
    public function edit($id = null)
    {
        $fraseId = $id ?? $this->request->getParam('id');
        
        if (!$fraseId) {
            return $this->jsonError('ID da frase não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $frase = $this->table->get($fraseId);
            $frase = $this->table->patchEntity($frase, $data);
            
            if ($this->table->save($frase)) {
                return $this->jsonSuccess($frase, 'Frase atualizada com sucesso');
            }
            
            return $this->jsonError('Erro ao atualizar frase', 422, $frase->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // DELETE /frases/delete/{id}
    public function delete($id = null)
    {
        $fraseId = $id ?? $this->request->getParam('id');
        
        if (!$fraseId) {
            return $this->jsonError('ID da frase não informado', 400);
        }
        
        try {
            $frase = $this->table->get($fraseId);
            
            if ($this->table->delete($frase)) {
                return $this->jsonSuccess(null, 'Frase excluída com sucesso');
            }
            
            return $this->jsonError('Erro ao excluir frase', 500);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
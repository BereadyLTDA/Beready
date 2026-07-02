<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class ImagensGeradasController extends AppController
{
    private $table;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('ImagensGeradas');
    }
    
    // GET /imagens/prompt/{promptId}
    public function getByPrompt($promptId = null)
    {
        if (!$promptId) {
            return $this->jsonError('ID do prompt não informado', 400);
        }
        
        try {
            $imagens = $this->table->find()
                ->where(['prompt_id' => $promptId])
                ->orderBy(['criado_em' => 'DESC'])
                ->all();
            
            return $this->jsonSuccess($imagens->toArray());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // GET /imagens/view/{id}
    public function view($id = null)
    {
        $imagemId = $id ?? $this->request->getParam('id');
        
        if (!$imagemId) {
            return $this->jsonError('ID da imagem não informado', 400);
        }
        
        try {
            $imagem = $this->table->get($imagemId);
            return $this->jsonSuccess($imagem);
        } catch (\Exception $e) {
            return $this->jsonError('Imagem não encontrada', 404);
        }
    }
    
    // POST /imagens
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        if (empty($data['prompt_id'])) {
            return $this->jsonError('ID do prompt é obrigatório', 400);
        }
        
        if (empty($data['url_imagem'])) {
            return $this->jsonError('URL da imagem é obrigatória', 400);
        }
        
        try {
            $imagem = $this->table->newEntity($data);
            
            if ($this->table->save($imagem)) {
                return $this->jsonSuccess($imagem, 'Imagem criada com sucesso', 201);
            }
            
            return $this->jsonError('Erro ao criar imagem', 422, $imagem->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // PUT /imagens/edit/{id}
    public function edit($id = null)
    {
        $imagemId = $id ?? $this->request->getParam('id');
        
        if (!$imagemId) {
            return $this->jsonError('ID da imagem não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $imagem = $this->table->get($imagemId);
            $imagem = $this->table->patchEntity($imagem, $data);
            
            if ($this->table->save($imagem)) {
                return $this->jsonSuccess($imagem, 'Imagem atualizada com sucesso');
            }
            
            return $this->jsonError('Erro ao atualizar imagem', 422, $imagem->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // DELETE /imagens/delete/{id}
    public function delete($id = null)
    {
        $imagemId = $id ?? $this->request->getParam('id');
        
        if (!$imagemId) {
            return $this->jsonError('ID da imagem não informado', 400);
        }
        
        try {
            $imagem = $this->table->get($imagemId);
            
            if ($this->table->delete($imagem)) {
                return $this->jsonSuccess(null, 'Imagem excluída com sucesso');
            }
            
            return $this->jsonError('Erro ao excluir imagem', 500);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
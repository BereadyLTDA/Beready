<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\TagService;
use App\Repositories\TagRepository;

class TagsController extends AppController
{
    private TagService $service;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->service = new TagService(new TagRepository());
    }
    
    // GET /tags
    public function index()
    {
        try {
            $tags = $this->service->getAllTags();
            return $this->jsonSuccess($tags);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // GET /tags/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId');
        
        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }
        
        try {
            $tags = $this->service->getTagsByUsuario((int)$userId);
            return $this->jsonSuccess($tags);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // GET /tags/view/{id}
    public function view($id = null)
    {
        $tagId = $id ?? $this->request->getParam('id');
        
        if (!$tagId) {
            return $this->jsonError('ID da tag não informado', 400);
        }
        
        try {
            $tag = $this->service->getTagById((int)$tagId);
            return $this->jsonSuccess($tag);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // POST /tags
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $tag = $this->service->createTag($data);
            return $this->jsonSuccess($tag, 'Tag criada com sucesso', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // PUT /tags/edit/{id}
    public function edit($id = null)
    {
        $tagId = $id ?? $this->request->getParam('id');
        
        if (!$tagId) {
            return $this->jsonError('ID da tag não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $tag = $this->service->updateTag((int)$tagId, $data);
            return $this->jsonSuccess($tag, 'Tag atualizada com sucesso');
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
    
    // DELETE /tags/delete/{id}
    public function delete($id = null)
    {
        $tagId = $id ?? $this->request->getParam('id');
        
        if (!$tagId) {
            return $this->jsonError('ID da tag não informado', 400);
        }
        
        try {
            $this->service->deleteTag((int)$tagId);
            return $this->jsonSuccess(null, 'Tag excluída com sucesso');
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
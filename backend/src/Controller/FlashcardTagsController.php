<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class FlashcardTagsController extends AppController
{
    private $table;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('FlashcardTags');
    }
    
    // GET /flashcard-tags/flashcard/{flashcardId}
    public function getByFlashcard($flashcardId = null)
    {
        if (!$flashcardId) {
            $this->response = $this->response->withStatus(400);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'ID do flashcard não informado'
            ]));
            return $this->response;
        }
        
        try {
            $relations = $this->table->find()
                ->where(['flashcard_id' => $flashcardId])
                ->contain(['Tags'])
                ->all();
            
            $result = [];
            foreach ($relations as $relation) {
                $item = $relation->toArray();
                if ($relation->has('tag')) {
                    $item['tag'] = $relation->tag->toArray();
                }
                $result[] = $item;
            }
            
            $this->response->getBody()->write(json_encode([
                'success' => true,
                'data' => $result
            ]));
            return $this->response;
            
        } catch (\Exception $e) {
            $this->response = $this->response->withStatus(500);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            return $this->response;
        }
    }
    
    // POST /flashcard-tags
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        if (empty($data['flashcard_id']) || empty($data['tag_id'])) {
            $this->response = $this->response->withStatus(400);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'flashcard_id e tag_id são obrigatórios'
            ]));
            return $this->response;
        }
        
        try {
            // Verifica se já existe
            $exists = $this->table->find()
                ->where([
                    'flashcard_id' => $data['flashcard_id'],
                    'tag_id' => $data['tag_id']
                ])
                ->first();
            
            if ($exists) {
                $this->response = $this->response->withStatus(409);
                $this->response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Tag já associada a este flashcard'
                ]));
                return $this->response;
            }
            
            $relation = $this->table->newEntity($data);
            
            if ($this->table->save($relation)) {
                $this->response = $this->response->withStatus(201);
                $this->response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Tag adicionada ao flashcard com sucesso',
                    'data' => $relation
                ]));
                return $this->response;
            }
            
            $this->response = $this->response->withStatus(422);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao associar tag',
                'errors' => $relation->getErrors()
            ]));
            return $this->response;
            
        } catch (\Exception $e) {
            $this->response = $this->response->withStatus(500);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => $e->getMessage()
            ]));
            return $this->response;
        }
    }
    
    // DELETE /flashcard-tags (com body)
    public function remove()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        if (empty($data['flashcard_id']) || empty($data['tag_id'])) {
            $this->response = $this->response->withStatus(400);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'flashcard_id e tag_id são obrigatórios'
            ]));
            return $this->response;
        }
        
        try {
            $relation = $this->table->find()
                ->where([
                    'flashcard_id' => $data['flashcard_id'],
                    'tag_id' => $data['tag_id']
                ])
                ->first();
            
            if (!$relation) {
                $this->response = $this->response->withStatus(404);
                $this->response->getBody()->write(json_encode([
                    'success' => false,
                    'message' => 'Associação não encontrada'
                ]));
                return $this->response;
            }
            
            if ($this->table->delete($relation)) {
                $this->response->getBody()->write(json_encode([
                    'success' => true,
                    'message' => 'Tag removida do flashcard com sucesso'
                ]));
                return $this->response;
            }
            
            $this->response = $this->response->withStatus(500);
            $this->response->getBody()->write(json_encode([
                'success' => false,
                'message' => 'Erro ao remover tag'
            ]));
            return $this->response;
            
        } catch (\Exception $e) {
            $this->response = $this->response->withStatus(500);
            $this->response->getBody()->write(json_encode([
                'success' => false,
            'message' => $e->getMessage()
            ]));
            return $this->response;
        }
    }
}
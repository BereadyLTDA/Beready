<?php
declare(strict_types=1);

namespace App\Controller;

use App\Services\FlashcardService;
use App\Repositories\FlashcardRepository;

class FlashcardsController extends AppController
{
    private FlashcardService $flashcardService;
    
    public function initialize(): void
    {
        parent::initialize();
        $this->flashcardService = new FlashcardService(new FlashcardRepository());
    }
    
    // GET /flashcards
    public function index()
    {
        try {
            $flashcards = $this->flashcardService->getAllFlashcards();
            return $this->jsonSuccess($flashcards);
        } catch (\Exception $e) {
            return $this->jsonError('Erro ao carregar flashcards: ' . $e->getMessage(), 500);
        }
    }
    
    // GET /flashcards/view/{id}
    public function view($id = null)
    {
        $flashcardId = $id ?? $this->request->getParam('id') ?? $this->request->getQuery('id');
        
        error_log("=== VIEW FLASHCARD ===");
        error_log("ID recebido: " . $flashcardId);
        
        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }
        
        try {
            $flashcard = $this->flashcardService->getFlashcardById((int)$flashcardId);
            return $this->jsonSuccess($flashcard);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    // POST /flashcards
    public function add()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        try {
            $flashcard = $this->flashcardService->createFlashcard($data);
            return $this->jsonSuccess($flashcard, 'Flashcard criado com sucesso', 201);
        } catch (\InvalidArgumentException $e) {
            return $this->jsonError($e->getMessage(), 400);
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    // PUT /flashcards/edit/{id}
    public function edit($id = null)
    {
        $flashcardId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');
        
        error_log("=== EDIT FLASHCARD ===");
        error_log("ID recebido: " . $flashcardId);
        
        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }
        
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();
        
        error_log("Dados recebidos: " . print_r($data, true));
        
        try {
            $flashcard = $this->flashcardService->updateFlashcard((int)$flashcardId, $data);
            return $this->jsonSuccess($flashcard, 'Flashcard atualizado com sucesso');
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            error_log("ERRO: " . $e->getMessage());
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
    
    // DELETE /flashcards/delete/{id}
    public function delete($id = null)
    {
        $flashcardId = $id ?? $this->request->getParam('id') ?? $this->request->getData('id');
        
        error_log("=== DELETE FLASHCARD ===");
        error_log("ID recebido: " . $flashcardId);
        
        if (!$flashcardId) {
            return $this->jsonError('ID do flashcard não informado', 400);
        }
        
        try {
            $this->flashcardService->deleteFlashcard((int)$flashcardId);
            return $this->jsonSuccess(null, 'Flashcard excluído com sucesso');
        } catch (\RuntimeException $e) {
            return $this->jsonError($e->getMessage(), $e->getCode() ?: 404);
        } catch (\Exception $e) {
            error_log("ERRO: " . $e->getMessage());
            return $this->jsonError('Erro interno: ' . $e->getMessage(), 500);
        }
    }
}
<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class ProgressoController extends AppController
{
    private $table;
    private $respostasTable;
    private $flashcardsTable;
    private $quizesTable;

    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('ProgressoUsuario');
        $this->respostasTable = TableRegistry::getTableLocator()->get('RespostasUsuario');
        $this->flashcardsTable = TableRegistry::getTableLocator()->get('Flashcards');
        $this->quizesTable = TableRegistry::getTableLocator()->get('Quizes');
    }

    // GET /progresso/usuario/{usuarioId}
    public function getByUsuario($usuarioId = null)
    {
        $userId = $usuarioId ?? $this->request->getParam('usuarioId') ?? $this->request->getQuery('usuarioId');

        if (!$userId) {
            return $this->jsonError('ID do usuário não informado', 400);
        }

        try {
            $progresso = $this->table->find()
                ->where(['usuario_id' => $userId])
                ->first();

            $data = $progresso
                ? $progresso->toArray()
                : [
                    'usuario_id' => (int)$userId,
                    'vocabulario_aprendido' => 0,
                    'flashcards_concluidos' => 0,
                    'quizes_concluidos' => 0,
                    'tempo_total_estudo' => 0,
                    'sequencia_atual' => 0,
                    'maior_sequencia' => 0,
                    'ultima_atividade' => null,
                    'progresso_nivel' => null,
                ];

            // --- Taxa de acerto (baseada em respostas_usuario) ---
            $totalRespostas = $this->respostasTable->find()
                ->where(['usuario_id' => $userId])
                ->count();

            $respostasCorretas = $this->respostasTable->find()
                ->where(['usuario_id' => $userId, 'correto' => true])
                ->count();

            $data['taxa_acerto'] = $totalRespostas > 0
                ? (int)round(($respostasCorretas / $totalRespostas) * 100)
                : 0;

            // --- Progresso geral (flashcards + quizzes concluídos vs total criado pelo usuário) ---
            $totalFlashcards = $this->flashcardsTable->find()
                ->where(['usuario_id' => $userId])
                ->count();

            $totalQuizes = $this->quizesTable->find()
                ->where(['usuario_id' => $userId])
                ->count();

            $percFlashcards = $totalFlashcards > 0
                ? ($data['flashcards_concluidos'] / $totalFlashcards)
                : 0;

            $percQuizes = $totalQuizes > 0
                ? ($data['quizes_concluidos'] / $totalQuizes)
                : 0;

            $data['progresso_geral'] = (int)round((($percFlashcards + $percQuizes) / 2) * 100);

            return $this->jsonSuccess($data);
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }

    // POST /progresso
    public function save()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();

        if (empty($data['usuario_id'])) {
            return $this->jsonError('ID do usuário é obrigatório', 400);
        }

        try {
            $existing = $this->table->find()
                ->where(['usuario_id' => $data['usuario_id']])
                ->first();

            if ($existing) {
                $entity = $this->table->patchEntity($existing, $data);
            } else {
                $entity = $this->table->newEntity($data);
            }

            if ($this->table->save($entity)) {
                return $this->jsonSuccess($entity, 'Progresso salvo com sucesso');
            }

            return $this->jsonError('Erro ao salvar progresso', 422, $entity->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}
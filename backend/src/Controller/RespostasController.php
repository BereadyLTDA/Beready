<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\ORM\TableRegistry;

class RespostasController extends AppController
{
    private $table;

    public function initialize(): void
    {
        parent::initialize();
        $this->table = TableRegistry::getTableLocator()->get('RespostasUsuario');
    }

    // POST /respostas
    // Body esperado: { usuario_id, tipo: 'flashcard'|'quiz', referencia_id, correto: true|false }
    public function save()
    {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true) ?: $this->request->getData();

        if (empty($data['usuario_id']) || empty($data['tipo']) || empty($data['referencia_id']) || !isset($data['correto'])) {
            return $this->jsonError('Dados incompletos para registrar resposta', 400);
        }

        try {
            $entity = $this->table->newEntity($data);

            if ($this->table->save($entity)) {
                return $this->jsonSuccess($entity, 'Resposta registrada com sucesso');
            }

            return $this->jsonError('Erro ao registrar resposta', 422, $entity->getErrors());
        } catch (\Exception $e) {
            return $this->jsonError($e->getMessage(), 500);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FlashcardRepositoryInterface;
use Cake\Datasource\ConnectionManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;

class FlashcardService
{
    public function __construct(
        private FlashcardRepositoryInterface $flashcardRepository,
        private RepeticaoEspacadaService $repeticaoEspacada,
        private ProgressoService $progresso,
    ) {
    }

    public function getFlashcardsByUsuario(int $usuarioId): array
    {
        return $this->flashcardRepository->findByUsuarioId($usuarioId);
    }

    public function getFlashcardById(int $id): array
    {
        $flashcard = $this->flashcardRepository->findById($id);
        if (!$flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
        return $flashcard;
    }

    public function getFlashcardByUuid(string $uuid): array
    {
        $flashcard = $this->flashcardRepository->findByUuid($uuid);
        if (!$flashcard) {
            throw new \RuntimeException('Flashcard não encontrado', 404);
        }
        return $flashcard;
    }

    public function createFlashcard(int $usuarioId, array $data): array
    {
        if (empty($data['frente'])) {
            throw new \InvalidArgumentException('A pergunta (frente) é obrigatória');
        }

        if (empty($data['verso'])) {
            throw new \InvalidArgumentException('A resposta (verso) é obrigatória');
        }

        $data['usuario_id'] = $usuarioId;

        return $this->flashcardRepository->create($data);
    }

    /**
     * O chamador deve validar o dono antes (getFlashcardById).
     */
    public function updateFlashcard(int $id, array $data): array
    {
        unset($data['usuario_id']);

        return $this->flashcardRepository->update($id, $data);
    }

    public function deleteFlashcard(int $id): bool
    {
        return $this->flashcardRepository->delete($id);
    }

    /**
     * Flashcards do usuário com revisão vencida e a contagem total (a lista pode ser limitada).
     *
     * @return array{total: int, flashcards: array}
     */
    public function getDevidos(int $usuarioId, ?int $limite = null): array
    {
        $agora = DateTime::now();

        return [
            'total' => $this->flashcardRepository->countDevidos($usuarioId, $agora),
            'flashcards' => $this->flashcardRepository->findDevidos($usuarioId, $agora, $limite),
        ];
    }

    /**
     * Grava a avaliação do estudo ('errei' | 'bom' | 'facil'): reagenda o card (SM-2), registra a
     * resposta (errei = incorreta) e incrementa os flashcards concluídos do usuário.
     * O chamador deve validar o dono antes (getFlashcardById).
     */
    public function revisar(int $flashcardId, int $usuarioId, string $nota): array
    {
        return ConnectionManager::get('default')->transactional(
            fn () => $this->registrarRevisao($flashcardId, $usuarioId, $nota)
        );
    }

    private function registrarRevisao(int $flashcardId, int $usuarioId, string $nota): array
    {
        $flashcard = $this->getFlashcardById($flashcardId);
        $agendamento = $this->repeticaoEspacada->calcular($flashcard, $nota, DateTime::now());

        $atualizado = $this->flashcardRepository->salvarAgendamento($flashcardId, [
            'repeticoes' => $agendamento['repeticoes'],
            'intervalo_dias' => $agendamento['intervalo_dias'],
            'fator_ease' => number_format($agendamento['fator_ease'], 2, '.', ''),
            'ultima_revisao' => DateTime::createFromInterface($agendamento['ultima_revisao']),
            'proxima_revisao' => DateTime::createFromInterface($agendamento['proxima_revisao']),
        ]);

        $respostas = TableRegistry::getTableLocator()->get('RespostasUsuario');
        $respostas->saveOrFail($respostas->newEntity([
            'usuario_id' => $usuarioId,
            'tipo' => 'flashcard',
            'referencia_id' => $flashcardId,
            'correto' => $nota !== 'errei',
        ]));

        $this->progresso->incrementarFlashcards($usuarioId, 1);

        return $atualizado;
    }
}

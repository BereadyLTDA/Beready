<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class ProgressoUsuario extends Entity
{
    protected array $_accessible = [
        'usuario_id' => true,
        'vocabulario_aprendido' => true,
        'flashcards_concluidos' => true,
        'quizes_concluidos' => true,
        'tempo_total_estudo' => true,
        'sequencia_atual' => true,
        'maior_sequencia' => true,
        'ultima_atividade' => true,
        'progresso_nivel' => true,
        'atualizado_em' => true,
        'user' => true,
    ];
}
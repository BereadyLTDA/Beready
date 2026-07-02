<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class FrasesSemelhante extends Entity
{
    protected array $_accessible = [
        'prompt_id' => true,
        'frase_semelhante' => true,
        'pontuacao_semelhante' => true,
        'tipo_frase' => true,
        'nivel_dificuldade' => true,
        'criado_em' => true,
        'prompt' => true,
    ];
}
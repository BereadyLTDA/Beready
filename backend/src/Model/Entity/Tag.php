<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Tag extends Entity
{
    protected array $_accessible = [
        'criado_por' => true,
        'nome' => true,
        'cor' => true,
        'descricao' => true,
        'tag_sistema' => true,
        'criado_em' => true,
        'user' => true,
    ];
}
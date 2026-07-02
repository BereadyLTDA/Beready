<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class FlashcardTag extends Entity
{
    protected array $_accessible = [
        'flashcard_id' => true,
        'tag_id' => true,
        'criado_em' => true,
        'flashcard' => true,
        'tag' => true,
    ];
}
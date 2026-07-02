<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Prompt extends Entity
{
    protected array $_accessible = [
        'usuario_id' => true,
        'texto_original' => true,
        'idioma_original' => true,
        'contexto' => true,
        'midia_origem_id' => true,
        'sessao_id' => true,
        'criado_em' => true,
        'user' => true,
        'traducoes' => true,
        'imagens_geradas' => true,
        'frases_semelhantes' => true,
    ];
}
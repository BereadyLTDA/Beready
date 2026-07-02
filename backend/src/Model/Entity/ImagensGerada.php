<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class ImagensGerada extends Entity
{
    protected array $_accessible = [
        'prompt_id' => true,
        'traducao_id' => true,
        'url_imagem' => true,
        'prompt_imagem' => true,
        'servico_geracao' => true,
        'qualidade_imagem' => true,
        'tamanho_arquivo' => true,
        'dimensoes' => true,
        'criado_em' => true,
        'prompt' => true,
        'traducao' => true,
    ];
}
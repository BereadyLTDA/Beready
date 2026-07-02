<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class Traducao extends Entity
{
    protected array $_accessible = [
        'prompt_id' => true,
        'texto_traduzido' => true,
        'idioma_destino' => true,
        'pontuacao_confianca' => true,
        'servico_traducao' => true,
        'traducoes_alternativas' => true,
        'criado_em' => true,
        'prompt' => true,
    ];
    
    protected function _getTraducoesAlternativas($value)
    {
        if ($value) {
            return json_decode($value, true);
        }
        return null;
    }
}
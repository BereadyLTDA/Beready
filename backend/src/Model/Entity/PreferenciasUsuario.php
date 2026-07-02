<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

class PreferenciasUsuario extends Entity
{
    protected array $_accessible = [
        'usuario_id' => true,
        'tema' => true,
        'modo_daltonico' => true,
        'notificacoes_ativas' => true,
        'som_ativo' => true,
        'traducao_automatica' => true,
        'preferencia_dificuldade' => true,
        'meta_diaria_minutos' => true,
        'criado_em' => true,
        'atualizado_em' => true,
        'user' => true,
    ];
}
<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Vocabulario Entity
 *
 * @property int $id
 * @property int $usuario_id
 * @property string|null $palavra_frase
 * @property \Cake\I18n\DateTime|null $criado_em
 *
 * @property \App\Model\Entity\Usuario $usuario
 */
class Vocabulario extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'usuario_id' => true,
        'palavra_frase' => true,
        'criado_em' => true,
        'usuario' => true,
    ];
}

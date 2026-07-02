<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Flashcard Entity
 *
 * @property int $id
 * @property int|null $usuario_id
 * @property string|null $frente
 * @property string|null $verso
 * @property string|null $nivel_dificuldade
 * @property \Cake\I18n\DateTime|null $criado_em
 * @property \Cake\I18n\DateTime|null $atualizado_em
 *
 * @property \App\Model\Entity\User $usuario
 */
class Flashcard extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'usuario_id' => true,
        'frente' => true,
        'verso' => true,
        'nivel_dificuldade' => true,
        'criado_em' => true,
        'atualizado_em' => true,
    ];
}
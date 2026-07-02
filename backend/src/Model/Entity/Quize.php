<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Quize Entity
 *
 * @property int $id
 * @property int $usuario_id
 * @property string|null $titulo
 * @property string|null $descricao
 * @property string|null $tipo_criacao
 * @property string|null $nivel_dificuldade
 * @property int|null $total_questoes
 * @property int|null $tempo_limite
 * @property bool|null $publico
 * @property \Cake\I18n\DateTime|null $criado_em
 * @property \Cake\I18n\DateTime|null $atualizado_em
 *
 * @property \App\Model\Entity\Usuario $usuario
 */
class Quize extends Entity
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
        'titulo' => true,
        'descricao' => true,
        'tipo_criacao' => true,
        'nivel_dificuldade' => true,
        'total_questoes' => true,
        'tempo_limite' => true,
        'publico' => true,
        'criado_em' => true,
        'atualizado_em' => true,
        'usuario' => true,
    ];
}

<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class RespostasUsuarioTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('respostas_usuario');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'criado_em' => 'new',
                ],
            ],
        ]);

        $this->belongsTo('Users', [
            'foreignKey' => 'usuario_id',
            'joinType' => 'INNER',
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('usuario_id')
            ->requirePresence('usuario_id', 'create')
            ->notEmptyString('usuario_id');

        $validator
            ->scalar('tipo')
            ->inList('tipo', ['flashcard', 'quiz'])
            ->requirePresence('tipo', 'create')
            ->notEmptyString('tipo');

        $validator
            ->integer('referencia_id')
            ->requirePresence('referencia_id', 'create')
            ->notEmptyString('referencia_id');

        $validator
            ->boolean('correto')
            ->requirePresence('correto', 'create');

        return $validator;
    }
}

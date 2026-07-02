<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class QuizesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('quizes');
        $this->setDisplayField('titulo');
        $this->setPrimaryKey('id');

        $this->belongsTo('Users', [
            'foreignKey' => 'usuario_id',
            'joinType' => 'INNER',
            'className' => 'Users'
        ]);
        
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'criado_em' => 'new',
                    'atualizado_em' => 'always'
                ]
            ]
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('usuario_id')
            ->notEmptyString('usuario_id');

        $validator
            ->scalar('titulo')
            ->maxLength('titulo', 200)
            ->requirePresence('titulo', 'create')
            ->notEmptyString('titulo');

        $validator
            ->scalar('descricao')
            ->allowEmptyString('descricao');

        $validator
            ->scalar('tipo_criacao')
            ->allowEmptyString('tipo_criacao');

        $validator
            ->scalar('nivel_dificuldade')
            ->allowEmptyString('nivel_dificuldade');

        $validator
            ->integer('total_questoes')
            ->allowEmptyString('total_questoes');

        $validator
            ->integer('tempo_limite')
            ->allowEmptyString('tempo_limite');

        $validator
            ->boolean('publico')
            ->allowEmptyString('publico');

        return $validator;
    }
}
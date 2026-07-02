<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ProgressoUsuarioTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('progresso_usuario');
        $this->setPrimaryKey('id');
        $this->setDisplayField('id');
        
        $this->belongsTo('Users', [
            'foreignKey' => 'usuario_id',
            'joinType' => 'INNER'
        ]);
    }
    
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');
        
        $validator
            ->integer('usuario_id')
            ->requirePresence('usuario_id', 'create')
            ->notEmptyString('usuario_id');
        
        $validator
            ->integer('vocabulario_aprendido')
            ->allowEmptyString('vocabulario_aprendido');
        
        $validator
            ->integer('flashcards_concluidos')
            ->allowEmptyString('flashcards_concluidos');
        
        $validator
            ->integer('quizes_concluidos')
            ->allowEmptyString('quizes_concluidos');
        
        $validator
            ->integer('tempo_total_estudo')
            ->allowEmptyString('tempo_total_estudo');
        
        $validator
            ->integer('sequencia_atual')
            ->allowEmptyString('sequencia_atual');
        
        $validator
            ->integer('maior_sequencia')
            ->allowEmptyString('maior_sequencia');
        
        return $validator;
    }
}
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class FrasesSemelhantesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('frases_semelhantes');
        $this->setPrimaryKey('id');
        
        $this->belongsTo('Prompts', [
            'foreignKey' => 'prompt_id',
            'joinType' => 'INNER'
        ]);
    }
    
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');
        
        $validator
            ->integer('prompt_id')
            ->requirePresence('prompt_id', 'create')
            ->notEmptyString('prompt_id');
        
        $validator
            ->scalar('frase_semelhante')
            ->requirePresence('frase_semelhante', 'create')
            ->notEmptyString('frase_semelhante');
        
        $validator
            ->decimal('pontuacao_semelhante')
            ->allowEmptyString('pontuacao_semelhante');
        
        return $validator;
    }
}
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TraducoesTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('traducoes');
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
            ->scalar('texto_traduzido')
            ->requirePresence('texto_traduzido', 'create')
            ->notEmptyString('texto_traduzido');
        
        $validator
            ->scalar('idioma_destino')
            ->maxLength('idioma_destino', 10)
            ->allowEmptyString('idioma_destino');
        
        $validator
            ->decimal('pontuacao_confianca')
            ->allowEmptyString('pontuacao_confianca');
        
        return $validator;
    }
}
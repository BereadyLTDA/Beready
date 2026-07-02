<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class TagsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('tags');
        $this->setPrimaryKey('id');
        $this->setDisplayField('nome');
        
        $this->belongsTo('Users', [
            'foreignKey' => 'criado_por',
            'joinType' => 'LEFT'
        ]);
    }
    
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');
        
        $validator
            ->scalar('nome')
            ->maxLength('nome', 100)
            ->requirePresence('nome', 'create')
            ->notEmptyString('nome')
            ->add('nome', 'unique', ['rule' => 'validateUnique', 'provider' => 'table']);
        
        $validator
            ->scalar('cor')
            ->maxLength('cor', 7)
            ->allowEmptyString('cor');
        
        $validator
            ->scalar('descricao')
            ->allowEmptyString('descricao');
        
        $validator
            ->boolean('tag_sistema')
            ->allowEmptyString('tag_sistema');
        
        return $validator;
    }
}
<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PromptsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('prompts');
        $this->setPrimaryKey('id');
        
        $this->belongsTo('Users', [
            'foreignKey' => 'usuario_id',
            'joinType' => 'LEFT'
        ]);
        
        $this->hasMany('Traducoes', [
            'foreignKey' => 'prompt_id'
        ]);
        
        $this->hasMany('ImagensGeradas', [
            'foreignKey' => 'prompt_id'
        ]);
        
        $this->hasMany('FrasesSemelhantes', [
            'foreignKey' => 'prompt_id'
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
            ->scalar('texto_original')
            ->requirePresence('texto_original', 'create')
            ->notEmptyString('texto_original');
        
        $validator
            ->scalar('idioma_original')
            ->maxLength('idioma_original', 10)
            ->allowEmptyString('idioma_original');
        
        $validator
            ->scalar('contexto')
            ->maxLength('contexto', 20)
            ->allowEmptyString('contexto');
        
        return $validator;
    }
}
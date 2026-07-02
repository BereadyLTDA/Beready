<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class ImagensGeradasTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('imagens_geradas');
        $this->setPrimaryKey('id');
        
        $this->belongsTo('Prompts', [
            'foreignKey' => 'prompt_id',
            'joinType' => 'LEFT'
        ]);
        
        $this->belongsTo('Traducoes', [
            'foreignKey' => 'traducao_id',
            'joinType' => 'LEFT'
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
            ->scalar('url_imagem')
            ->requirePresence('url_imagem', 'create')
            ->notEmptyString('url_imagem');
        
        $validator
            ->scalar('servico_geracao')
            ->maxLength('servico_geracao', 50)
            ->allowEmptyString('servico_geracao');
        
        return $validator;
    }
}
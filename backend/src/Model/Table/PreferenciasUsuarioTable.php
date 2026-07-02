<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class PreferenciasUsuarioTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('preferencias_usuario');
        $this->setPrimaryKey('id');
        
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
            ->scalar('tema')
            ->maxLength('tema', 20)
            ->allowEmptyString('tema');
        
        $validator
            ->boolean('modo_daltonico')
            ->allowEmptyString('modo_daltonico');
        
        $validator
            ->boolean('notificacoes_ativas')
            ->allowEmptyString('notificacoes_ativas');
        
        return $validator;
    }
}
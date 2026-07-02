<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;
use Ramsey\Uuid\Uuid;

class UsersTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->setTable('users');
        $this->setDisplayField('nome');
        $this->setPrimaryKey('id');
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'criado_em' => 'new',
                    'atualizado_em' => 'always'
                ]
            ]
        ]);
    }
    
    public function beforeSave($event, $entity, $options)
    {
        if ($entity->isNew() && !$entity->uuid) {
            $entity->uuid = Uuid::uuid4()->toString();
        }
        return true;
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('nome')
            ->maxLength('nome', 100)
            ->allowEmptyString('nome');

        $validator
            ->email('email')
            ->allowEmptyString('email');

        $validator
            ->scalar('senha_hash')
            ->maxLength('senha_hash', 255)
            ->allowEmptyString('senha_hash');

        $validator
            ->scalar('telefone')
            ->maxLength('telefone', 20)
            ->allowEmptyString('telefone');

        $validator
            ->scalar('nivel_ingles')
            ->maxLength('nivel_ingles', 20)
            ->allowEmptyString('nivel_ingles');

        $validator
            ->scalar('idioma_preferido')
            ->maxLength('idioma_preferido', 10)
            ->allowEmptyString('idioma_preferido');

        $validator
            ->scalar('status')
            ->maxLength('status', 20)
            ->allowEmptyString('status');

        return $validator;
    }
}
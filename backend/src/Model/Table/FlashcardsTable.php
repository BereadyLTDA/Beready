<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Flashcards Model 
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 * @property \App\Model\Table\TagsTable&\Cake\ORM\Association\BelongsToMany $Tags
 */
class FlashcardsTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('flashcards');
        $this->setDisplayField('frente');
        $this->setPrimaryKey('id');

        
        // Timestamp behavior
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'criado_em' => 'new',
                    'atualizado_em' => 'always'
                ]
            ]
        ]);

        // Relacionamento com Users
        $this->belongsTo('Users', [
            'foreignKey' => 'usuario_id',
            'joinType' => 'LEFT',
            'className' => 'Users'
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('usuario_id')
            ->allowEmptyString('usuario_id');

        $validator
            ->scalar('frente')
            ->maxLength('frente', 1000)
            ->allowEmptyString('frente');

        $validator
            ->scalar('verso')
            ->maxLength('verso', 2000)
            ->allowEmptyString('verso');

        $validator
            ->scalar('nivel_dificuldade')
            ->maxLength('nivel_dificuldade', 20)
            ->allowEmptyString('nivel_dificuldade');

        return $validator;
    }
}
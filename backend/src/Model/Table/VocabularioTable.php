<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Vocabulario Model
 *
 * @property \App\Model\Table\UsuariosTable&\Cake\ORM\Association\BelongsTo $Usuarios
 *
 * @method \App\Model\Entity\Vocabulario newEmptyEntity()
 * @method \App\Model\Entity\Vocabulario newEntity(array $data, array $options = [])
 * @method array<\App\Model\Entity\Vocabulario> newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Vocabulario get(mixed $primaryKey, array|string $finder = 'all', \Psr\SimpleCache\CacheInterface|string|null $cache = null, \Closure|string|null $cacheKey = null, mixed ...$args)
 * @method \App\Model\Entity\Vocabulario findOrCreate($search, ?callable $callback = null, array $options = [])
 * @method \App\Model\Entity\Vocabulario patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method array<\App\Model\Entity\Vocabulario> patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Vocabulario|false save(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method \App\Model\Entity\Vocabulario saveOrFail(\Cake\Datasource\EntityInterface $entity, array $options = [])
 * @method iterable<\App\Model\Entity\Vocabulario>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vocabulario>|false saveMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Vocabulario>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vocabulario> saveManyOrFail(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Vocabulario>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vocabulario>|false deleteMany(iterable $entities, array $options = [])
 * @method iterable<\App\Model\Entity\Vocabulario>|\Cake\Datasource\ResultSetInterface<\App\Model\Entity\Vocabulario> deleteManyOrFail(iterable $entities, array $options = [])
 */
class VocabularioTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('vocabulario');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('Usuarios', [
            'foreignKey' => 'usuario_id',
            'joinType' => 'INNER',
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('usuario_id')
            ->notEmptyString('usuario_id');

        $validator
            ->scalar('palavra_frase')
            ->maxLength('palavra_frase', 200)
            ->allowEmptyString('palavra_frase');

        $validator
            ->dateTime('criado_em')
            ->allowEmptyDateTime('criado_em');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn(['usuario_id'], 'Usuarios'), ['errorField' => 'usuario_id']);

        return $rules;
    }
}

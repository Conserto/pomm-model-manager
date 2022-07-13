<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Test\Unit\Model;

use Mock\PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntity as FlexibleEntityMock;
use Mock\PommProject\ModelManager\Model\RowStructure as RowStructureMock;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Pager;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\Where;
use PommProject\ModelManager\Converter\PgEntity;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;
use PommProject\ModelManager\Model\Model as PommModel;
use PommProject\ModelManager\Test\Fixture\ComplexFixtureModel;
use PommProject\ModelManager\Test\Fixture\ComplexNumber;
use PommProject\ModelManager\Test\Fixture\ComplexNumberStructure;
use PommProject\ModelManager\Test\Fixture\ReadFixtureModel;
use PommProject\ModelManager\Test\Fixture\SimpleFixture;
use PommProject\ModelManager\Test\Fixture\SimpleFixtureModel;
use PommProject\ModelManager\Test\Fixture\WeirdFixture;
use PommProject\ModelManager\Test\Fixture\WeirdFixtureModel;
use PommProject\ModelManager\Test\Fixture\WithoutPKFixtureModel;
use PommProject\ModelManager\Test\Fixture\WriteFixtureModel;
use PommProject\ModelManager\Test\Unit\BaseTest;

class Model extends BaseTest
{
    /**
     * @throws FoundationException
     */
    protected function initializeSession(Session $session)
    {
        $session
            ->getPoolerForType('converter')
            ->getConverterHolder()
            ->registerConverter(
                'ComplexNumber',
                new PgEntity(ComplexNumber::class, new ComplexNumberStructure()),
                ['pomm_test.complex_number']
            )
            ;
    }

    protected function getSimpleFixtureModel(Session $session)
    {
        return $session
            ->getModel(SimpleFixtureModel::class)
            ;
    }

    protected function getReadFixtureModel(Session $session)
    {
        return $session
            ->getModel(ReadFixtureModel::class)
            ;
    }

    protected function getWriteFixtureModel(Session $session)
    {
        return $session
            ->getModel(WriteFixtureModel::class)
            ;
    }

    protected function getWithoutPKFixtureModel(Session $session)
    {
        return $session
            ->getModel(WithoutPKFixtureModel::class)
            ;
    }

    protected function getComplexFixtureModel(Session $session)
    {
        return $session
            ->getModel(ComplexFixtureModel::class);
    }

    protected function getWeirdFixtureModel(Session $session)
    {
        return $session
            ->getModel(WeirdFixtureModel::class);
    }

    public function testGetClientType()
    {
        $this
            ->string($this->getSimpleFixtureModel($this->buildSession())->getClientType())
            ->isEqualTo('model')
            ;
    }

    public function getClientIdentifier()
    {
        $this
            ->string($this->getSimpleFixtureModel($this->buildSession())->getClientIdentifier())
            ->isEqualTo(SimpleFixtureModel::class)
            ;
    }

    /**
     * @throws FoundationException
     */
    public function testCreateProjection()
    {
        $session = $this->buildSession();
        $model = $this->getSimpleFixtureModel($session);

        $this
            ->object($model->createProjection())
            ->isInstanceOf(\PommProject\ModelManager\Model\Projection::class)
            ->array($model->createProjection()->getFieldTypes())
            ->isIdenticalTo(['id' => 'int4', 'a_varchar' => 'varchar', 'a_boolean' => 'bool'])
            ;
    }

    /**
     * @throws FoundationException
     */
    public function testGetStructure()
    {
        $session = $this->buildSession();
        $model = $this->getSimpleFixtureModel($session);

        $this
            ->object($model->getStructure())
            ->isInstanceOf(\PommProject\ModelManager\Model\RowStructure::class)
            ->array($model->getStructure()->getDefinition())
            ->isIdenticalTo(['id' => 'int4', 'a_varchar' => 'varchar', 'a_boolean' => 'bool'])
            ;
    }

    /**
     * @throws FoundationException
     * @throws ModelException
     * @throws \ReflectionException
     */
    public function testInitialize()
    {
        $session = $this->buildSession();
        $this
            ->exception(function () use ($session) {
                    $model = new NoStructureNoFlexibleEntityModel();
                    $model->initialize($session);
                })
            ->isInstanceOf(ModelException::class)
            ->exception(function () use ($session) {
                    $model = new NoFlexibleEntityModel();
                    $model->initialize($session);
                })
            ->isInstanceOf(ModelException::class)
            ->exception(function () use ($session) {
                    $model = new NoStructureModel();
                    $model->initialize($session);
                })
            ->isInstanceOf(ModelException::class)
            ;
    }

    public function testQuery()
    {
        $session = $this->buildSession();
        $model = $this->getSimpleFixtureModel($session);
        $where = new Where('id % $* = 0', [2]);
        $this
            ->object($model->doSimpleQuery())
            ->isInstanceOf(\PommProject\ModelManager\Model\CollectionIterator::class)
            ->integer($model->doSimpleQuery()->count())
            ->isEqualTo(4)
            ->integer($model->doSimpleQuery()->count())
            ->isEqualTo(4)
            ->integer($model->doSimpleQuery($where)->count())
            ->isEqualTo(2)
            ;
    }

    public function testFindAll()
    {
        $session = $this->buildSession();
        $model = $this->getReadFixtureModel($session);
        $this
            ->object($model->findAll())
            ->isInstanceOf(\PommProject\ModelManager\Model\CollectionIterator::class)
            ->array($model->findAll()->slice('id'))
            ->isIdenticalTo([1, 2, 3, 4])
            ->array($model->findAll('order by id desc')->slice('id'))
            ->isIdenticalTo([4, 3, 2, 1])
            ->array($model->findAll('limit 3')->slice('id'))
            ->isIdenticalTo([1, 2, 3, ])
            ;
        $complex_model = $this->getComplexFixtureModel($session);
        $entity = $complex_model->findAll('order by id asc limit 1')->current();
        $this
            ->object($entity)
            ->isInstanceOf(\PommProject\ModelManager\Test\Fixture\ComplexFixture::class)
            ;
    }

    public function testFindWhere()
    {
        $model = $this->getReadFixtureModel($this->buildSession());
        $condition = 'id % $* = 0';
        $where = new Where($condition, [2]);
        $this
            ->object($model->findWhere('true'))
            ->isInstanceOf(\PommProject\ModelManager\Model\CollectionIterator::class)
            ->array($model->findWhere($condition, [2])->slice('id'))
            ->isIdenticalTo($model->findWhere($where)->slice('id'))
            ->integer($model->findWhere($where)->count())
            ->isEqualTo(2)
            ->array($model->findWhere($condition, [1], 'order by id desc limit 3')->slice('id'))
            ->isIdenticalTo([4, 3, 2])
            ;
    }

    public function testFindByPK()
    {
        $model = $this->getReadFixtureModel($this->buildSession());
        $model_without_pk = $this->getWithoutPKFixtureModel($this->buildSession());
        $model_weird = $this->getWeirdFixtureModel($this->buildSession());
        $this
            ->object($model->findByPK(['id' => 1]))
            ->isInstanceOf(SimpleFixture::class)
            ->integer($model->findByPK(['id' => 2])['id'])
            ->isEqualTo(2)
            ->variable($model->findByPK(['id' => 5]))
            ->isNull()
            ->integer($model->findByPK(['id' => 3])->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_EXIST)
            ->exception(function () use ($model_without_pk) { $model_without_pk->findByPK(['id' => 1]); })
            ->isInstanceOf(ModelException::class)
            ->message->contains("has no primary key.")
            ->exception(function () use ($model) { $model->findByPK(['a_varchar' => 'one']); })
            ->isInstanceOf(ModelException::class)
            ->message->contains("Key 'id' is missing to fully describes the primary key")
            ->object($model_weird->findByPK(['field_a' => 2, 'field_b' => false]))
            ->isInstanceOf(WeirdFixture::class)
            ;
    }

    public function testUseIdentityMapper()
    {
        $model = $this->getReadFixtureModel($this->buildSession());
        $this
            ->object($model->findByPK(['id' => 1]))
            ->isIdenticalTo($model->findByPK(['id' => 1]))
            ;
    }

    public function testCountWhere()
    {
        $model = $this->getReadFixtureModel($this->buildSession());
        $condition = 'id % $* = 0';
        $where = new Where($condition, [2]);
        $this
            ->integer($model->countWhere('true'))
            ->isEqualTo(4)
            ->integer($model->countWhere($condition, [2]))
            ->isEqualTo(2)
            ->integer($model->countWhere($where))
            ->isEqualTo(2)
            ;
    }

    public function testExistWhere()
    {
        $model = $this->getReadFixtureModel($this->buildSession());
        $condition = 'a_varchar = $*';
        $this
            ->boolean($model->existWhere('true'))
            ->isTrue()
            ->boolean($model->existWhere($condition, ['one']))
            ->isTrue()
            ->boolean($model->existWhere($condition, ['aqwzxedc']))
            ->isFalse()
            ->boolean($model->existWhere(new Where($condition, ['two'])))
            ->isTrue()
            ;
    }


    public function testPaginateFindWhere()
    {
        $model = $this->getReadFixtureModel($this->buildSession());
        $pager = $model->paginateFindWhere(new Where, 2);
        $this
            ->object($pager)
            ->isInstanceOf(Pager::class)
            ->array($pager->getIterator()->slice('id'))
            ->isIdenticalTo([1, 2])
            ->array($model->paginateFindWhere(new Where, 2, 2, 'order by id desc')->getIterator()->slice('id'))
            ->isIdenticalTo([2, 1])
            ;
    }

    public function testInsertOne()
    {
        $model = $this->getWriteFixtureModel($this->buildSession());
        $entity = new SimpleFixture(['a_varchar' => 'e', 'undefined_field' => null]);
        $this
            ->object($model->insertOne($entity))
            ->isIdenticalTo($model)
            ->boolean($entity->hasId())
            ->isTrue()
            ->boolean($entity->status() === FlexibleEntityInterface::STATUS_EXIST)
            ->isTrue()
            ;
    }

    public function testUpdateOne()
    {
        $model = $this->getWriteFixtureModel($this->buildSession());
        $model_without_pk = $this->getWithoutPKFixtureModel($this->buildSession());
        $entity = $model->createAndSave(['a_varchar' => 'qwerty', 'a_boolean' => false]);
        $entity_without_pk = $model_without_pk->createAndSave(['id' => 1, 'a_varchar' => 'qwerty', 'a_boolean' => false]);
        $entity->set('a_varchar', 'azerty')->set('a_boolean', true);
        $entity_without_pk->set('a_varchar', 'azerty')->set('a_boolean', true);
        $this
            ->assert('Simple update')
            ->object($model->updateOne($entity, ['a_varchar']))
            ->isIdenticalTo($model)
            ->string($entity->get('a_varchar'))
            ->isEqualTo('azerty')
            ->boolean($entity->get('a_boolean'))
            ->isFalse()
            ->boolean($entity->status() === FlexibleEntityInterface::STATUS_EXIST)
            ->isTrue()
            ->exception(function () use ($model_without_pk, $entity_without_pk) { $model_without_pk->updateOne($entity_without_pk, ['a_varchar']); })
            ->isInstanceOf(ModelException::class)
            ->message->contains("has no primary key.")
        ;
        $entity->set('a_boolean', ! $entity->get('a_boolean'));
        $model->updateOne($entity, ['a_boolean']);
        $this
            ->boolean($entity->get('a_boolean'))
            ->isTrue()
            ;

        $entity->set('a_boolean', false);
        $model->updateOne($entity);
        $this
            ->boolean($entity->get('a_boolean'))
            ->isFalse()
        ;
    }

    public function testUpdateByPK()
    {
        $model = $this->getWriteFixtureModel($this->buildSession());
        $model_without_pk = $this->getWithoutPKFixtureModel($this->buildSession());
        $entity = $model->createAndSave(['a_varchar' => 'qwerty', 'a_boolean' => false]);
        $updated_entity = $model->updateByPk(['id' => $entity['id']], ['a_boolean' => true]);
        $this
            ->object($updated_entity)
            ->isInstanceOf(SimpleFixture::class)
            ->boolean($updated_entity['a_boolean'])
            ->isTrue()
            ->integer($updated_entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_EXIST)
            ->variable($model->updateByPk(['id' => 999999], ['a_varchar' => 'whatever']))
            ->isNull()
            ->object($entity)
            ->isIdenticalTo($updated_entity)
            ->exception(function () use ($model_without_pk) { $model_without_pk->updateByPk(['id' => 1],  ['a_varchar' => 'whatever']); })
            ->isInstanceOf(ModelException::class)
            ->message->contains("has no primary key.")

        ;
    }

    public function testDeleteOne()
    {
        $model = $this->getWriteFixtureModel($this->buildSession());
        $model_without_pk = $this->getWithoutPKFixtureModel($this->buildSession());
        $entity_without_pk = $model_without_pk->createAndSave(['id' => 1, 'a_varchar' => 'qwerty', 'a_boolean' => false]);
        $entity = $model->createAndSave(['a_varchar' => 'mlkjhgf']);
        $this
            ->object($model->deleteOne($entity))
            ->isInstanceOf(WriteFixtureModel::class)
            ->variable($model->findByPK(['id' => $entity['id']]))
            ->isNull()
            ->integer($entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_NONE)
            ->exception(function () use ($model_without_pk, $entity_without_pk) { $model_without_pk->deleteOne($entity_without_pk); })
            ->isInstanceOf(ModelException::class)
            ->message->contains("has no primary key.")
            ;
    }

    public function testDeleteByPK()
    {
        $model = $this->getWriteFixtureModel($this->buildSession());
        $model_without_pk = $this->getWithoutPKFixtureModel($this->buildSession());
        $entity_without_pk = $model_without_pk->createAndSave(['id' => 1, 'a_varchar' => 'qwerty', 'a_boolean' => false]);
        $entity = $model->createAndSave(['a_varchar' => 'qwerty', 'a_boolean' => false]);
        $deleted_entity = $model->deleteByPK(['id' => $entity['id']]);
        $this
            ->object($deleted_entity)
            ->isInstanceOf(SimpleFixture::class)
            ->integer($deleted_entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_NONE)
            ->variable($model->deleteByPK(['id' => $entity['id']]))
            ->isNull()
            ->object($entity)
            ->isIdenticalTo($deleted_entity)
            ->integer($entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_NONE)
            ->exception(function () use ($model_without_pk, $entity_without_pk) { $model_without_pk->deleteOne($entity_without_pk); })
            ->isInstanceOf(ModelException::class)
            ->message->contains("has no primary key.")
            ;
    }

    public function testDeleteWhere()
    {
        $model = $this->getWriteFixtureModel($this->buildSession());
        $entity1 = $model->createAndSave(['a_varchar' => 'qwerty', 'a_boolean' => false]);
        $entity2 = $model->createAndSave(['a_varchar' => 'qwertz', 'a_boolean' => true]);
        $deleted_entities = $model->deleteWhere('a_varchar = $*::varchar', ['qwertz']);
        $this
            ->object($deleted_entities)
            ->isInstanceOf(\PommProject\ModelManager\Model\CollectionIterator::class)
            ->integer($deleted_entities->count())
            ->isEqualTo(1)
            ->object($deleted_entities->get(0))
            ->isInstanceOf(SimpleFixture::class)
            ->isEqualTo($entity2)
            ->integer($deleted_entities->get(0)->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_NONE)
        ;

        $deleted_entities2 = $model->deleteWhere('a_varchar = $*::varchar', ['qwertz']);
        $this
            ->object($deleted_entities2)
            ->isInstanceOf(\PommProject\ModelManager\Model\CollectionIterator::class)
            ->integer($deleted_entities2->count())
            ->isEqualTo(0)
           ;

        $deleted_entities3 = $model->deleteWhere(
            Where::create('a_boolean = $*::boolean', [false])
        );

        $this
            ->object($deleted_entities3)
            ->isInstanceOf(\PommProject\ModelManager\Model\CollectionIterator::class)
            ->integer($deleted_entities3->count())
            ->isEqualTo(1)
            ->object($deleted_entities3->get(0))
            ->isInstanceOf(SimpleFixture::class)
            ->isEqualTo($entity1)
        ;
    }

    /**
     * @throws FoundationException
     */
    public function testCreateAndSave()
    {
        $session = $this->buildSession();
        $model   = $this->getWriteFixtureModel($session);
        $entity  = $model->createAndSave(['a_varchar' => 'abcdef', 'a_boolean' => true]);
        $this
            ->boolean($entity->has('id'))
            ->isTrue()
            ->string($entity->get('a_varchar'))
            ->isEqualTo('abcdef')
            ->integer($entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_EXIST)
            ->object($model->findWhere('id = $*', [$entity['id']])->current())
            ->isIdenticalTo($entity)
            ;
    }

    /**
     * @throws FoundationException
     */
    public function testCreateEntity()
    {
        $session = $this->buildSession();
        $model   = $this->getSimpleFixtureModel($session);
        $entity  = $model->createEntity();
        $this
            ->object($entity)
            ->isInstanceOf(SimpleFixture::class);
    }

    /**
     * @throws FoundationException
     */
    public function testGetModel()
    {
        $this
            ->given($model = $this->getSimpleFixtureModel($this->buildSession()))
            ->boolean($model->testGetModel())
            ->isTrue()
        ;
    }
}

class NoStructureNoFlexibleEntityModel extends PommModel
{
}

class NoStructureModel extends PommModel
{
    public function __construct()
    {
        $this->flexible_entity_class = 'something';
    }
}

class NoFlexibleEntityModel extends PommModel
{
    public function __construct()
    {
        $this->structure = new RowStructureMock();
    }
}

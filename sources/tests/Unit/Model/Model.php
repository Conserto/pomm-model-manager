<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Test\Unit\Model;

use PommProject\Foundation\Where;
use PommProject\Foundation\Query\QueryPooler;
use PommProject\Foundation\PreparedQuery\PreparedQueryPooler;
use PommProject\Foundation\Test\Unit\Converter\BaseConverter;

use PommProject\ModelManager\Test\Fixture\SimpleFixture;
use PommProject\ModelManager\Model\Model as PommModel;
use PommProject\ModelManager\Model\ModelPooler;

use Mock\PommProject\ModelManager\Model\FlexibleEntity as FlexibleEntityMock;
use Mock\PommProject\ModelManager\Model\RowStructure   as RowStructureMock;

class Model extends BaseConverter
{
    public function setUp()
    {
        $connection = $this
            ->getSession()
            ->getConnection()
            ;
        $connection->executeAnonymousQuery('drop schema if exists pomm_test cascade; create schema pomm_test');
    }

    public function tearDown()
    {
        $this
            ->getSession()
            ->getConnection()
            ->executeAnonymousQuery('drop schema pomm_test cascade;');
    }

    protected function registerClientPoolers()
    {
        parent::registerClientPoolers();
        $this->session
            ->registerClientPooler(new PreparedQueryPooler())
            ->registerClientPooler(new QueryPooler())
            ->registerClientPooler(new ModelPooler())
            ;
    }

    protected function getSimpleFixtureModel()
    {
        return $this->getSession()
            ->getModel('PommProject\ModelManager\Test\Fixture\SimpleFixtureModel')
            ;
    }

    protected function getReadFixtureModel()
    {
        return $this->getSession()
            ->getModel('PommProject\ModelManager\Test\Fixture\ReadFixtureModel')
            ;
    }

    protected function getWriteFixtureModel()
    {
        return $this->getSession()
            ->getModel('PommProject\ModelManager\Test\Fixture\WriteFixtureModel')
            ;
    }

    public function testGetClientType()
    {
        $this
            ->string($this->getSimpleFixtureModel()->getClientType())
            ->isEqualTo('model')
            ;
    }

    public function getClientIdentifier()
    {
        $this
            ->string($this->getSimpleFixtureModel()->getClientIdentifier())
            ->isEqualTo('PommProject\ModelManager\Test\Fixture\SimpleFixtureModel')
            ;
    }

    public function testInitialize()
    {
        $session = $this->getSession();
        $this
            ->exception(function() use ($session) {
                    $model = new NoStructureNoFlexibleEntityModel();
                    $model->initialize($session);
                })
            ->isInstanceOf('\PommProject\ModelManager\Exception\ModelException')
            ->exception(function() use ($session) {
                    $model = new NoFlexibleEntityModel();
                    $model->initialize($session);
                })
            ->isInstanceOf('\PommProject\ModelManager\Exception\ModelException')
            ->exception(function() use ($session) {
                    $model = new NoStructureModel();
                    $model->initialize($session);
                })
            ->isInstanceOf('\PommProject\ModelManager\Exception\ModelException')
            ;
    }

    public function testQuery()
    {
        $model = $this->getSimpleFixtureModel();
        $where = new Where('id % $* = 0', [2]);
        $this
            ->object($model->doSimpleQuery())
            ->isInstanceOf('\PommProject\ModelManager\Model\CollectionIterator')
            ->integer($model->doSimpleQuery()->count())
            ->isEqualTo(4)
            ->integer($model->doSimpleQuery()->count())
            ->isEqualTo(4)
            ->integer($model->doSimpleQuery($where)->count())
            ->isEqualTo(2)
            ;
    }

    public function testGetPrimaryKey()
    {
        $this
            ->array($this->getSimpleFixtureModel()->getPrimaryKey())
            ->isIdenticalTo(['id'])
            ;
    }

    public function testCreateProjection()
    {
        $this
            ->object($this->getSimpleFixtureModel()->createProjection())
            ->isInstanceOf('\PommProject\ModelManager\Model\Projection')
            ;
    }

    public function testFindAll()
    {
        $model = $this->getReadFixtureModel();
        $this
            ->object($model->findAll())
            ->isInstanceOf('\PommProject\ModelManager\Model\CollectionIterator')
            ->array($model->findAll()->slice('id'))
            ->isIdenticalTo([1, 2, 3, 4])
            ->array($model->findAll('order by id desc')->slice('id'))
            ->isIdenticalTo([4, 3 ,2 ,1])
            ->array($model->findAll('limit 3')->slice('id'))
            ->isIdenticalTo([1, 2, 3,])
            ;
    }

    public function testFindWhere()
    {
        $model = $this->getReadFixtureModel();
        $condition = 'id % $* = 0';
        $where = new Where($condition, [2]);
        $this
            ->object($model->findWhere('true'))
            ->isInstanceOf('\PommProject\ModelManager\Model\CollectionIterator')
            ->array($model->findWhere($condition, [2])->slice('id'))
            ->isIdenticalTo($model->findWhere($where)->slice('id'))
            ->integer($model->findWhere($where)->count())
            ->isEqualTo(2)
            ->array($model->findWhere($condition, [1], 'order by id desc limit 3')->slice('id'))
            ->isIdenticalTo([4, 3, 2])
            ;
    }

    public function testCountWhere()
    {
        $model = $this->getReadFixtureModel();
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

    public function testInsertOne()
    {
        $model = $this->getWriteFixtureModel();
        $entity = new SimpleFixture(['a_varchar' => 'e']);
        $this
            ->object($model->insertOne($entity))
            ->isIdenticalTo($model)
            ->boolean($entity->hasId())
            ->isTrue()
            ->boolean($entity->status() === FlexibleEntityMock::EXIST)
            ->isTrue()
            ;
    }

    public function testUpdateOne()
    {
        $model  = $this->getWriteFixtureModel();
        $entity = $model->createAndSave(['a_varchar' => 'qwerty', 'a_boolean' => false]);
        $entity->set('a_varchar', 'azerty')->set('a_boolean', true);
        $this
            ->object($model->updateOne($entity, ['a_varchar']))
            ->isIdenticalTo($model)
            ->string($entity->get('a_varchar'))
            ->isEqualTo('azerty')
            ->boolean($entity->get('a_boolean'))
            ->isFalse()
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
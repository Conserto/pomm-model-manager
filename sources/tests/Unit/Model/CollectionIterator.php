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

use Mock\PommProject\ModelManager\Model\CollectionIterator as CollectionIteratorMock;
use Mock\PommProject\ModelManager\Model\Projection as ProjectionMock;
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Test\Fixture\SimpleFixtureModel;
use PommProject\ModelManager\Tester\ModelSessionAtoum;

class CollectionIterator extends ModelSessionAtoum
{
    protected $session;

    protected function getSession()
    {
        if ($this->session === null) {
            $this->session = $this->buildSession();
        }

        return $this->session;
    }

    protected function initializeSession(Session $session): void
    {
        $session
            ->registerClient(new SimpleFixtureModel)
            ;
    }

    protected function getSql()
    {
        return <<<SQL
select
    id, some_data
from
    (values (1, 'one'), (2, 'two'), (3, 'three'), (4, 'four'))
        pika (id, some_data)
SQL;
    }

    protected function getQueryResult($sql)
    {
        $sql ??= $this->getSql();

        return $this->getSession()->getConnection()->sendQueryWithParameters($sql);
    }

    protected function getCollectionMock($sql = null)
    {
        return new CollectionIteratorMock(
            $this->getQueryResult($sql),
            $this->getSession(),
            new ProjectionMock(\PommProject\ModelManager\Test\Fixture\SimpleFixture::class, ['id' => 'int4', 'some_data' => 'varchar'])
        );
    }

    public function testGetWithoutFilters(): void
    {
        $collection = $this->getCollectionMock();
        $this
            ->object($collection->get(0))
            ->isInstanceOf(\PommProject\ModelManager\Test\Fixture\SimpleFixture::class)
            ->mock($collection)
            ->call('parseRow')
            ->atLeastOnce()
            ->array($collection->get(0)->extract())
            ->isEqualTo(['Id' => 1, 'SomeData' => 'one'])
            ->array($collection->get(3)->extract())
            ->isEqualTo(['Id' => 4, 'SomeData' => 'four'])
            ;
    }

    public function testGetWithFilters(): void
    {
        $collection = $this->getCollectionMock();
        $collection->registerFilter(
            function (array $values) { $values['id'] *= 2; return $values; }
        )
            ->registerFilter(
                function (array $values) {
                    $values['some_data'] =
                        strlen((string) $values['some_data']) > 3
                        ? null
                        : $values['some_data'];
                    ++$values['id'];
                    $values['new_value'] = 'love pomm';

                    return $values;
                }
        );
        $this
            ->array($collection->get(0)->extract())
            ->isEqualTo(['Id' => 3, 'SomeData' => 'one', 'NewValue' => 'love pomm'])
            ->array($collection->get(3)->extract())
            ->isEqualTo(['Id' => 9, 'SomeData' => null, 'NewValue' => 'love pomm'])
            ;
    }

    public function testGetWithWrongFilter(): void
    {
        $collection = $this->getCollectionMock();
        $collection->registerFilter(fn($values) => $values['id']);
        $this
            ->exception(function () use ($collection): void { $collection->get(2); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('Filters MUST return an array')
            ;
    }

    public function testExtract(): void
    {
        $collection = $this->getCollectionMock();

        $this
            ->array($collection->extract())
            ->isIdenticalTo(
                [
                    ['Id' => 1, 'SomeData' => 'one'],
                    ['Id' => 2, 'SomeData' => 'two'],
                    ['Id' => 3, 'SomeData' => 'three'],
                    ['Id' => 4, 'SomeData' => 'four'],
                ]
            );
    }

    public function testSlice(): void
    {
        $collection = $this->getCollectionMock();

        $this
            ->array($collection->slice('some_data'))
            ->isIdenticalTo(
                [
                    'one',
                    'two',
                    'three',
                    'four',
                ]
            );
    }
}

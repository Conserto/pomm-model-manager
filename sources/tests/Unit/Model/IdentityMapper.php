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

use atoum\atoum;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;
use PommProject\ModelManager\Test\Fixture\ComplexFixture;
use PommProject\ModelManager\Test\Fixture\ComplexFixtureStructure;

class IdentityMapper extends atoum\test
{
    public function testFetch()
    {
        $fixture = new ComplexFixture([
            'created_at' => new \DateTime("2014-10-30 10:13:56.420342+00"),
            'some_id' => 1,
            'yes' => true
        ]);
        $mapper = $this->newTestedInstance();

        $fixture = $mapper->fetch($fixture, ['some_id'], new ComplexFixtureStructure());
        $this
            ->object($fixture)
            ->isInstanceOf(ComplexFixture::class)
            ->dateTime($fixture->get('created_at'))
            ->hasYear(2014)
            ->boolean($fixture->get('yes'))
            ->isTrue()
            ->integer($fixture->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_EXIST)
        ;
        $fixture = $mapper->fetch(
            new ComplexFixture([
                    'created_at' => new \DateTime("2013-10-30 10:13:56.420342+00"),
                    'some_id' => 1,
                    'yes' => false ]
            ),
            ['some_id'],
            new ComplexFixtureStructure()
        );
        $this->object($fixture)
            ->dateTime($fixture->get('created_at'))
            ->hasYear(2013)
            ->boolean($fixture->get('yes'))
            ->isFalse()
            ->object($mapper->clear())
        ;
        $fixture = $mapper->fetch($fixture, ['some_id', 'created_at'], new ComplexFixtureStructure());
        $this->object($fixture)
            ->dateTime($fixture->get('created_at'))
            ->hasYear(2013)
            ->boolean($fixture->get('yes'))
            ->isFalse()
            ->integer($fixture->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_EXIST);
        $fixture = $mapper->fetch(new ComplexFixture([
                'created_at' => new \DateTime("2013-10-30 10:13:56.420342+00"),
                'some_id' => 1, 'yes' => true
            ]), ['some_id', 'created_at'], new ComplexFixtureStructure());
        $this->object($fixture)
            ->boolean($fixture->get('yes'))
            ->isTrue()
            ;
    }
}

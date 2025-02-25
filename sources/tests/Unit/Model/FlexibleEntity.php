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
use PommProject\ModelManager\Model\FlexibleEntity as PommFlexibleEntity;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

class FlexibleEntity extends atoum\test
{
    public function testConstructorEmpty(): void
    {
        $entity = new PikaEntity();
        $this
            ->object($entity)
            ->isInstanceOf(PommFlexibleEntity::class)
            ->array($entity->fields())
            ->isEmpty()
            ;
    }

    public function testConstructorWithParameters(): void
    {
        $entity = new ChuEntity(['pika' => 'whatever']);
        $this
            ->array($entity->fields())
            ->isIdenticalTo(['chu' => true, 'pika' => 'whatever'])
            ;
        $entity = new ChuEntity(['pika' => 'whatever', 'chu' => false]);
        $this
            ->array($entity->fields())
            ->isIdenticalTo(['chu' => false, 'pika' => 'whatever'])
            ;
    }

    public function testGet(): void
    {
        $entity = new PikaEntity(['pika' => 'whatever', 'an_array' => [1, 2]]);
        $this
            ->string($entity->get('pika'))
            ->isEqualTo('whatever')
            ->array($entity->get('an_array'))
            ->isIdenticalTo([1, 2])
            ->exception(function () use ($entity): void { $entity->get('no_such_key'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('No such key')
            ;
        PommFlexibleEntity::$strict = false;
        $this
            ->variable($entity->get('no_such_key'))
            ->isNull()
            ;
        PommFlexibleEntity::$strict = true;
    }

    public function testHas(): void
    {
        $entity = new ChuEntity(['pika' => 'whatever']);
        $this
            ->boolean($entity->has('pika'))
            ->isTrue()
            ->boolean($entity->has('chu'))
            ->isTrue()
            ->boolean($entity->has('no_such_key'))
            ->isFalse()
            ;
    }

    public function testSet(): void
    {
        $entity = new PikaEntity([]);
        $this
            ->string($entity->set('chu', 'whatever')->get('chu'))
            ->isEqualTo('whatever')
            ->integer($entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_MODIFIED)
            ->string($entity->set('chu', 'pika')->get('chu'))
            ->isEqualTo('pika')
            ->array($entity->set('an_array', [1, 2])->get('an_array'))
            ->isIdenticalTo([1, 2])
            ;
    }

    public function testAdd(): void
    {
        $entity = new PikaEntity(['pika' => 'whatever', 'an_array' => []]);
        $this
            ->array($entity->add('an_array', 1)->get('an_array'))
            ->isIdenticalTo([1])
            ->integer($entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_MODIFIED)
            ->array($entity->add('an_array', 2)->get('an_array'))
            ->isIdenticalTo([1, 2])
            ->Exception(function () use ($entity): void { $entity->add('pika', 3); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('is not an array')
            ->array($entity->add('whatever', 1)->get('whatever'))
            ->isIdenticalTo([1])
            ;
    }

    public function testClear(): void
    {
        $entity = new ChuEntity(['pika' => 'whatever']);
        $this
            ->boolean($entity->clear('pika')->has('pika'))
            ->isFalse()
            ->boolean($entity->has('chu'))
            ->isTrue()
            ->boolean($entity->clear('chu')->has('chu'))
            ->isFalse()
            ;
    }

    public function testUnderscoreCall(): void
    {
        $entity = new PikaEntity();
        $this
            ->exception(function () use ($entity): void { $entity->eDqSdgeDsTfd(); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('No such method')
            ->exception(function () use ($entity): void { $entity->sefPika(); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('No such method')
            ;
    }

    public function testUnderscoreCallGet(): void
    {
        $entity = new PikaEntity(['pika' => 'whatever', 'chu' => [1, 2]]);
        $this
            ->string($entity->getPika())
            ->isEqualTo('WHATEVER')
            ->array($entity->getChu())
            ->isIdenticalTo([1, 2])
            ->exception(function () use ($entity): void { $entity->getNoSuchKey(); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('No such key')
            ;
    }

    public function testUnderscoreCallSet(): void
    {
        $entity = new PikaEntity();
        $this
            ->string($entity->setChu('a value')->get('chu'))
            ->isEqualTo('a value')
            ;
    }

    public function testUnderscoreCallAdd(): void
    {
        $entity = new PikaEntity(['pika' => 'whatever', 'chu' => [1, 2]]);
        $this
            ->array($entity->addChu(3)->get('chu'))
            ->isIdenticalTo([1, 2, 3])
            ;
    }

    public function testUnderscoreCallHas(): void
    {
        $entity = new PikaEntity(['chu' => [1, 2]]);
        $this
            ->boolean($entity->hasPika())
            ->isFalse()
            ->boolean($entity->hasChu())
            ->isTrue()
            ;
    }

    public function testUnderscoreCallClear(): void
    {
        $entity = new PikaEntity(['pika' => 'whatever']);
        $this
            ->boolean($entity->clearPika()->hasPika())
            ->isFalse()
            ;
    }

    public function testHydrate(): void
    {
        $entity = new ChuEntity(['pika' => 'whatever']);
        $this
            ->array($entity->hydrate(['chu' => null, 'an_array' => [1, 2]])->fields())
            ->isIdenticalTo(['chu' => null, 'pika' => 'whatever', 'an_array' => [1, 2]])
            ->array($entity->hydrate([])->fields())
            ->isIdenticalTo(['chu' => null, 'pika' => 'whatever', 'an_array' => [1, 2]])
            ;
    }

    public function testConvert(): void
    {
        $entity = new PikaEntity();
        $this
            ->array($entity->convert(['WhAtEveR' => 'WoW', 'PikA' => ''])->fields())
            ->isIdenticalTo(['whatever' => 'WoW', 'pika' => ''])
            ;
    }

    public function testExtract(): void
    {
        $entity = new PikaEntity();
        $this
            ->array($entity->extract())
            ->isEmpty()
            ->array($entity->set('pika', 2)->extract())
            ->isIdenticalTo(['pika' => 2, 'pika_hash' => 'c81e728d9d4c2f636f067f89cc14862c'])
            ->array($entity->set('an_entity', new ChuEntity())->extract())
            ->isIdenticalTo(['pika' => 2, 'an_entity' => ['chu' => true], 'pika_hash' => 'c81e728d9d4c2f636f067f89cc14862c'])
            ->array($entity->set('an_array', [1, 'whatever'])->extract())
            ->isIdenticalTo([
                'pika' => 2,
                'an_entity' => ['chu' => true],
                'an_array' => [1, 'whatever'],
                'pika_hash' => 'c81e728d9d4c2f636f067f89cc14862c',
            ])
            ->array($entity->set('entity_array', [new ChuEntity(), new ChuEntity(['pika' => 1])])->extract())
            ->isIdenticalTo([
                'pika' => 2,
                'an_entity' => ['chu' => true],
                'an_array' => [1, 'whatever'],
                'entity_array' => [['chu' => true], ['chu' => true, 'pika' => 1]],
                'pika_hash' => 'c81e728d9d4c2f636f067f89cc14862c',
                ])
            ;
    }

    public function testUnderscoreSet(): void
    {
        $entity = new PikaEntity();
        $entity->chu = 'WoW';
        $entity->pika = 'WoW';
        $this
            ->array($entity->get(['pika', 'chu']))
            ->isIdenticalTo(['chu' => 'wow', 'pika' => 'WoW'])
            ;
    }

    public function testUnderscoreGet(): void
    {
        $entity = new PikaEntity(['pika' => 'WoW', 'chu' => 'WoW']);
        $this
            ->string($entity->pika)
            ->isEqualTo('WOW')
            ->string($entity->chu)
            ->isEqualTo('WoW')
            ->exception(function () use ($entity): void { $entity->whatever; })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('No such key')
            ;
    }

    public function testStatus(): void
    {
        $entity = new PikaEntity();
        $this
            ->integer($entity->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_NONE)
            ->integer($entity->status(FlexibleEntityInterface::STATUS_MODIFIED)->status())
            ->isEqualTo(FlexibleEntityInterface::STATUS_MODIFIED)
            ;
    }

    public function testArrayAccess(): void
    {
        $entity = new PikaEntity();
        $entity['pika'] = 'wow';
        $entity['chu'] = 'WOW';
        $this
            ->array($entity->fields())
            ->isIdenticalTo(['pika' => 'wow', 'chu' => 'wow'])
            ->string($entity['pika'])
            ->isEqualTo('WOW')
            ->string($entity['chu'])
            ->isEqualTo('wow')
            ->exception(function () use ($entity): void { $entity['no_such_key']; })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('No such key')
            ->boolean(isset($entity['chu']))
            ->isTrue()
            ->boolean(isset($entity['no_such_key']))
            ->isFalse()
            ;
        unset($entity['pika']);
        $this
            ->boolean($entity->has('pika'))
            ->isFalse()
            ->boolean($entity->has('pika_hash'))
            ->isFalse()
            ;
    }

    public function testGetIterator(): void
    {
        $entity = new PikaEntity();
        $this
            ->object($entity->getIterator())
            ->isInstanceOf(\ArrayIterator::class)
            ->array($entity->getIterator()->getArrayCopy())
            ->isEmpty()
            ->array($entity->setPika('chu')->getIterator()->getArrayCopy())
            ->isIdenticalTo(['pika' => 'chu', 'pika_hash' => 'cbcefaf71b4677cb8bcc006e0aeaa34a'])
            ->object($entity->set('an_entity', new ChuEntity())->getIterator()->getArrayCopy()['an_entity'])
            ->isInstanceOf(PommFlexibleEntity::class)
            ->array($entity->set('an_array', [1, 2])->getIterator()->getArrayCopy()['an_array'])
            ->isIdenticalTo([1, 2])
            ;
    }

    public function testIsset(): void
    {
        $entity = new ChuEntity(['pika' => 'whatever']);
        $this
            ->boolean(isset($entity->pika))
            ->isTrue()
            ->boolean(isset($entity->no_such_key))
            ->isFalse()
        ;
    }

    public function testUnset(): void
    {
        $entity = new ChuEntity(['pika' => 'whatever']);
        $this
            ->boolean(isset($entity->pika))
            ->isTrue()
        ;
        unset($entity->pika);
        $this
            ->boolean(isset($entity->pika))
            ->isFalse()
        ;
    }

    public function testModifiedColumn(): void
    {
        $entity = new PikaEntity();
        $this
            ->array($entity->getModifiedColumns())
            ->isEmpty()
            ->array($entity->setChu('foo')->getModifiedColumns())
            ->isEqualTo(['chu'])
            ->array($entity->clearChu()->getModifiedColumns())
            ->isEmpty()
        ;
    }
}

class PikaEntity extends PommFlexibleEntity
{
    public function getPika(): string
    {
        return strtoupper((string) $this->get('pika'));
    }

    public function setChu($val): static
    {
        $this->set('chu', strtolower((string) $val));

        return $this;
    }

    public function getPikaHash(): string
    {
        return md5((string) $this->get('pika'));
    }

    public function hasPikaHash(): bool
    {
        return $this->has('pika');
    }
}

class ChuEntity extends PommFlexibleEntity
{
    public function __construct(array $values = [])
    {
        $this->set('chu', true);
        parent::__construct($values);
    }
}

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
use PommProject\ModelManager\Exception\ModelException;

class Projection extends atoum\test
{
    public function testConstructorEmpty(): void
    {
        $projection = $this->newTestedInstance('whatever');
        $this
            ->object($projection)
            ->isInstanceOf(\PommProject\ModelManager\Model\Projection::class)
            ->array($projection->getFieldNames())
            ->isEmpty()
            ;
    }

    public function testConstructorWithParameter(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this
            ->object($projection)
            ->isInstanceOf(\PommProject\ModelManager\Model\Projection::class)
            ->array($projection->getFieldNames())
            ->isIdenticalTo(['pika'])
            ->string($projection->getFieldType('pika'))
            ->isEqualTo('int4')
            ;
    }

    public function testSetField(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this
            ->array($projection->setField('chu', '%:chu:%', 'bool')->getFieldNames())
            ->isIdenticalTo(['pika', 'chu'])
            ->string($projection->getFieldType('chu'))
            ->isEqualTo('bool')
            ->array($projection->setField('chu', '%:chu:%', 'char')->getFieldNames())
            ->isIdenticalTo(['pika', 'chu'])
            ->string($projection->getFieldType('chu'))
            ->isEqualTo('char')
            ->array($projection->setField('chu', '%:chu:%', null)->getFieldNames())
            ->isIdenticalTo(['pika', 'chu'])
            ->variable($projection->getFieldType('chu'))
            ->isNull()
            ;
    }

    public function testSetFieldType(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this
            ->string($projection->setFieldType('pika', 'bool')->getFieldType('pika'))
            ->isEqualTo('bool')
            ->variable($projection->setFieldType('pika', null)->getFieldType('pika'))
            ->isNull()
            ->exception(function () use ($projection): void { $projection->setFieldType('whatever', 'whatever'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('does not exist')
            ;
    }

    public function testUnsetField(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this
            ->array($projection->unsetField('pika')->getFieldNames())
            ->isEmpty()
            ->exception(function () use ($projection): void { $projection->getFieldType('pika'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('does not exist')
            ->exception(function () use ($projection): void { $projection->unsetField('pika'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('does not exist')
            ;
    }

    public function testUnsetFields(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4', 'pok' => 'int4']);
        $this
            ->array($projection->unsetFields(['pika', 'pok'])->getFieldNames())
            ->isEmpty()
            ->exception(function () use ($projection): void { $projection->getFieldType('pika'); })
            ->isInstanceOf(ModelException::class)
            ->message->contains('does not exist')
            ->exception(function () use ($projection): void { $projection->unsetField('pok'); })
            ->isInstanceOf(ModelException::class)
            ->message->contains('does not exist')
        ;
    }

    public function testHasField(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this
            ->boolean($projection->hasField('pika'))
            ->isTrue()
            ->boolean($projection->hasField('chu'))
            ->isFalse()
            ;
    }

    public function testGetFieldType(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this
            ->string($projection->getFieldType('pika'))
            ->isEqualTo('int4')
            ->exception(function () use ($projection): void { $projection->getFieldType('chu'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('does not exist')
            ;
    }

    public function testIsArray(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this->boolean($projection->isArray('pika'))
            ->isFalse()
            ->boolean($projection->setField('chu', '%:chu:%', 'int4[]')->isArray('chu'))
            ->isTrue()
            ->exception(function () use ($projection): void { $projection->isArray('whatever'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('does not exist')
            ;
    }

    public function testGetFieldNames(): void
    {
        $projection = $this->newTestedInstance('whatever');
        $this->array($projection->getFieldNames())
            ->isEmpty()
            ->array($projection->setField('pika', '%:chu:%', 'int4')->getFieldNames())
            ->isIdenticalTo(['pika'])
            ->array($projection->setField('chu', '%:chu:%', 'char')->getFieldNames())
            ->isIdenticalTo(['pika', 'chu'])
            ;
    }

    public function testGetFieldTypes(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4'])
            ->setField('chu', 'expression(chu)')
            ->setField('plop', 'plop', 'string')
            ;

        $this
            ->array($projection->getFieldTypes())
            ->isIdenticalTo(['pika' => 'int4', 'chu' => null, 'plop' => 'string'])
            ;
    }

    public function testGetFieldWithTableAlias(): void
    {
        $projection = $this->newTestedInstance('whatever', ['pika' => 'int4']);
        $this->string($projection->getFieldWithTableAlias('pika'))
            ->isEqualTo('"pika"')
            ->string($projection->getFieldWithTableAlias('pika', 'my_table'))
            ->isEqualTo('my_table."pika"')
            ->string($projection->setField('chu', '%:pika:% / 2', 'int4')->getFieldWithTableAlias('chu', 'my_table'))
            ->isEqualTo('my_table."pika" / 2')
            ->exception(function () use ($projection): void { $projection->getFieldWithTableAlias('whatever'); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('does not exist')
            ;
    }

    public function testGetFieldsWithTableAlias(): void
    {
        $projection = $this->newTestedInstance('whatever');
        $this->array($projection->getFieldsWithTableAlias())
            ->isEmpty()
            ->array($projection->getFieldsWithTableAlias('my_table'))
            ->isEmpty()
            ->array($projection->getFieldsWithTableAlias(null))
            ->isEmpty()
            ->array($projection->setField('pika', '%:pika:%', 'int4')->getFieldsWithTableAlias())
            ->isIdenticalTo(['pika' => '"pika"'])
            ->array($projection->getFieldsWithTableAlias('my_table'))
            ->isIdenticalTo(['pika' => 'my_table."pika"'])
            ->array($projection->setField('chu', '%:chu:%', 'int4')->getFieldsWithTableAlias())
            ->isIdenticalTo(['pika' => '"pika"', 'chu' => '"chu"'])
            ->array($projection->getFieldsWithTableAlias('my_table'))
            ->isIdenticalTo(['pika' => 'my_table."pika"', 'chu' => 'my_table."chu"'])
            ;
    }

    public function testFormatFields(): void
    {
        $projection = $this->newTestedInstance('whatever');
        $this->string($projection->formatFields())
            ->isEmpty()
            ->string($projection->formatFields('my_table'))
            ->isEmpty()
            ->string($projection->formatFields(null))
            ->isEmpty()
            ->string($projection->setField('pika', '%:pika:%', 'int4')->formatFields())
            ->isEqualTo('"pika"')
            ->string($projection->formatFields('my_table'))
            ->isEqualTo('my_table."pika"')
            ->string($projection->formatFields(null))
            ->isEqualTo('"pika"')
            ->string($projection->setField('chu', '%:pika:% / 2', 'int4')->formatFields())
            ->isEqualTo('"pika", "pika" / 2')
            ->string($projection->formatFields('my_table'))
            ->isEqualTo('my_table."pika", my_table."pika" / 2')
            ;
    }

    public function testFormatFieldsWithTableAlias(): void
    {
        $projection = $this->newTestedInstance('whatever');
        $this->string($projection->formatFieldsWithFieldAlias())
            ->isEmpty()
            ->string($projection->formatFieldsWithFieldAlias('my_table'))
            ->isEmpty()
            ->string($projection->formatFieldsWithFieldAlias(null))
            ->isEmpty()
            ->string($projection->setField('pika', '%:pika:%', 'int4')->formatFieldsWithFieldAlias())
            ->isEqualTo('"pika" as "pika"')
            ->string($projection->formatFieldsWithFieldAlias('my_table'))
            ->isEqualTo('my_table."pika" as "pika"')
            ->string($projection->formatFieldsWithFieldAlias(null))
            ->isEqualTo('"pika" as "pika"')
            ->string($projection->setField('chu', '%:pika:% / 2', 'int4')->formatFieldsWithFieldAlias())
            ->isEqualTo('"pika" as "pika", "pika" / 2 as "chu"')
            ->string($projection->formatFieldsWithFieldAlias('my_table'))
            ->isEqualTo('my_table."pika" as "pika", my_table."pika" / 2 as "chu"')
            ;
    }
}

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
use PommProject\ModelManager\Model\RowStructure as PommRowStructure;

class RowStructure extends atoum\test
{
    public function testInherits()
    {
        $structure = new GoodStructure();
        $this->object($structure->inherits(new ChuStructure()))
            ->isInstanceOf(\PommProject\ModelManager\Model\RowStructure::class)
            ->array($structure->getDefinition())
            ->isIdenticalTo(['pika' => 'int4', 'chu' => 'bool'])
            ;
    }

    public function testAddField()
    {
        $structure = new GoodStructure();
        $this->array($structure->getDefinition())
            ->isIdenticalTo(['pika' => 'int4'])
            ->array($structure->addField('chu', 'bool')->getDefinition())
            ->isIdenticalTo(['pika' => 'int4', 'chu' => 'bool'])
            ;
    }

    public function testGetFieldNames()
    {
        $structure = new GoodStructure();
        $this->array($structure->getFieldNames())
            ->isIdenticalTo(['pika'])
            ->array($structure->addField('chu', 'bool')->getFieldNames())
            ->isIdenticalTo(['pika', 'chu'])
            ;
    }

    public function testHasField()
    {
        $structure = new GoodStructure();
        $this->boolean($structure->hasField('pika'))
            ->isTrue()
            ->boolean($structure->hasField('chu'))
            ->isFalse()
            ->boolean($structure->addField('chu', 'bool')->hasField('chu'))
            ->isTrue()
            ;
    }

    public function testGetTypeFor()
    {
        $structure = new GoodStructure();
        $this->string($structure->getTypeFor('pika'))
            ->isEqualTo('int4')
            ->exception(function () use ($structure) { $structure->getTypeFor('chu'); })
            ->isinstanceof(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains("Field 'chu' is not defined")
            ->string($structure->addField('chu', 'bool')->getTypeFor('chu'))
            ->isEqualTo('bool')
            ;
    }

    public function testGetDefinition()
    {
        $structure = new GoodStructure();
        $this->array($structure->getDefinition())
            ->isIdenticalTo(['pika' => 'int4'])
            ->array($structure->addField('chu', 'bool')->getDefinition())
            ->isIdenticalTo(['pika' => 'int4', 'chu' => 'bool'])
            ;
    }

    public function testGetRelation()
    {
        $structure = new GoodStructure();
        $this->string($structure->getRelation())
            ->isEqualTo('pika')
            ;
    }

    public function testGetPrimaryKey()
    {
        $structure = new GoodStructure();
        $this->array($structure->getPrimaryKey())
            ->isEmpty()
            ;
        $structure = new ChuStructure();
        $this->array($structure->getPrimaryKey())
            ->isIdenticalTo(['chu'])
            ;
    }

    public function testArrayAccess()
    {
        $structure = new GoodStructure();
        $this->string($structure['pika'])
            ->isEqualTo('int4')
            ;
        $structure['chu'] = 'bool';
        $this->boolean(isset($structure['chu']))
            ->isTrue()
            ->exception(function () use ($structure) { unset($structure['chu']); })
            ->isInstanceOf(\PommProject\ModelManager\Exception\ModelException::class)
            ->message->contains('Cannot unset a structure field')
            ;
    }
}

class GoodStructure extends PommRowStructure
{
    public function __construct()
    {
        $this->relation                  = 'pika';
        $this->fieldDefinitions['pika'] = 'int4';
    }
}

class ChuStructure extends PommRowStructure
{
    public function __construct()
    {
        $this->relation                 = 'chu';
        $this->fieldDefinitions['chu'] = 'bool';
        $this->primaryKey              = ['chu'];
    }
}

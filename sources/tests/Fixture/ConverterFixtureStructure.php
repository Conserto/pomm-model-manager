<?php

namespace PommProject\ModelManager\Test\Fixture;

use PommProject\ModelManager\Model\RowStructure;

class ConverterFixtureStructure extends RowStructure
{
    public function __construct()
    {
        $this
            ->setRelation('converter_fixture')
            ->setPrimaryKey(['id'])
            ->addField('id', 'int4')
            ->addField('label', 'varchar')
            ->addField('id_linked_fixture', 'int4')
            ->addField('created_at', 'timestamp')
        ;
    }
}

<?php

namespace PommProject\ModelManager\Test\Fixture;

use PommProject\ModelManager\Model\RowStructure;

class LinkedFixtureStructure extends RowStructure
{
    public function __construct()
    {
        $this
            ->setRelation('linked_fixture')
            ->setPrimaryKey(['id'])
            ->addField('id', 'int4')
            ->addField('label', 'varchar')
            ->addField('created_at', 'timestamp')
        ;
    }
}

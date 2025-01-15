<?php

namespace PommProject\ModelManager\Test\Fixture;

use PommProject\ModelManager\Model\Model;

class LinkedFixtureModel extends Model
{
    public function __construct()
    {
        $this->structure = new LinkedFixtureStructure();
        $this->flexibleEntityClass = LinkedFixture::class;
    }
}

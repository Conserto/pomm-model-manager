<?php

namespace PommProject\ModelManager\Test\Unit\Converter;

use PommProject\ModelManager\Test\Fixture\ConverterFixture;
use PommProject\ModelManager\Test\Fixture\ConverterFixtureModel;
use PommProject\ModelManager\Test\Fixture\LinkedFixture;
use PommProject\ModelManager\Test\Unit\BaseTest;

class ConverterPooler extends BaseTest
{
    public function testConverterPooler(): void
    {
        $session = $this->buildSession();
        $model = $session->getModel(ConverterFixtureModel::class);
        $entity = $model->findOneWithLinkedFixture();

        $this->object($entity)
            ->isInstanceOf(ConverterFixture::class)
            ->integer($entity['id'])
            ->isEqualTo(1)
            ->string($entity['label'])
            ->isEqualTo('converter_fixture_test')
            ->integer($entity['id_linked_fixture'])
            ->isEqualTo(2)
            ->isNotNull($entity['linked_fixture'])
        ;

        $linkedFixtureEntity = $entity->get('linked_fixture');
        $this->object($linkedFixtureEntity)
            ->isInstanceOf(LinkedFixture::class)
            ->integer($linkedFixtureEntity['id'])
            ->isEqualTo(2)
            ->string($linkedFixtureEntity['label'])
            ->isEqualTo('linked_fixture_test')
        ;
    }
}

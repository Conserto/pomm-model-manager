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

use PommProject\Foundation\Converter\ConverterHolder;
use PommProject\Foundation\Converter\ConverterPooler;
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Test\Fixture\SimpleFixtureModel;
use PommProject\ModelManager\Tester\VanillaSessionAtoum;

class ModelPooler extends VanillaSessionAtoum
{
    protected function initializeSession(Session $session): void
    {
        $session
            ->registerClientPooler(new ConverterPooler(new ConverterHolder))
            ->registerClientPooler($this->newTestedInstance())
            ;
    }

    public function testGetPoolerType(): void
    {
        $this
            ->string($this->newTestedInstance()->getPoolerType())
            ->isEqualTo('model')
            ;
    }

    public function testGetClient(): void
    {
        $session = $this->buildSession();
        $model_class = SimpleFixtureModel::class;
        $model_instance = $session->getClientUsingPooler('model', $model_class);

        $this
            ->assert('Client is not in the ClientHolder.')
            ->object($model_instance)
            ->isInstanceOf($model_class)
            ->object($session->getClientUsingPooler('model', $model_class))
            ->isIdenticalTo($model_instance)
            ;
    }
}

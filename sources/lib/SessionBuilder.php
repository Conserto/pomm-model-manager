<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager;

use PommProject\Foundation\Client\ClientHolder;
use PommProject\Foundation\Session\Connection;
use PommProject\Foundation\Session\Session;
use PommProject\Foundation\SessionBuilder as FoundationSessionBuilder;
use PommProject\ModelManager\Converter\ConverterPooler;
use PommProject\ModelManager\Model\ModelPooler;
use PommProject\ModelManager\ModelLayer\ModelLayerPooler;
use PommProject\ModelManager\Session as ModelManagerSession;

/**
 * Session builder for the ModelManager package.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       FoundationSessionBuilder
 */
class SessionBuilder extends FoundationSessionBuilder
{
    /** Register ModelManager's poolers. */
    protected function postConfigure(Session $session): SessionBuilder
    {
        parent::postConfigure($session);
        $modelPooler = new ModelPooler();
        $session
            ->registerClientPooler($modelPooler)
            ->registerClientPooler(new ModelLayerPooler);

        // replace converter pooler to activate the dynamic model converter
        /** @var \PommProject\Foundation\Converter\ConverterPooler $registeredConverterPooler */
        $registeredConverterPooler =  $session->getPoolerForType('converter');
        $converterPooler = new ConverterPooler(
            $registeredConverterPooler->getConverterHolder(),
            $modelPooler
        );
        $session->registerClientPooler($converterPooler);

        return $this;
    }


    /** @see VanillaSessionBuilder */
    protected function createSession(Connection $connection, ClientHolder $clientHolder, ?string $stamp): Session
    {
        $this->configuration->setDefaultValue('class:session', ModelManagerSession::class);

        /** @var \PommProject\ModelManager\Session $session */
        $session = parent::createSession($connection, $clientHolder, $stamp);
        return $session;
    }
}

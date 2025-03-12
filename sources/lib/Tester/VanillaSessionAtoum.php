<?php
/*
 * This file is part of the PommProject/Foundation package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Tester;

use PommProject\Foundation\Exception\FoundationException;
use Atoum;
use PommProject\ModelManager\Session;
use PommProject\ModelManager\SessionBuilder;
use PommProject\Foundation\Session\Session as FoundationSession;
use PommProject\Foundation\Tester\VanillaSessionAtoum as FoundationVanillaSessionAtoum;

/**
 * This is a Session aware Atoum class. It uses the vanilla session builder hence produce session with no poolers nor
 * clients. It is intended to be overloaded by each package to add their own poolers.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
abstract class VanillaSessionAtoum extends FoundationVanillaSessionAtoum
{
    private ?SessionBuilder $sessionBuilder = null;

    /**
     * A short description here
     *
     * @throws FoundationException
     */
    protected function buildSession(?string $stamp = null): Session
    {
        /** @var Session $session */
        $session = $this->getSessionBuilder()->buildSession($stamp);
        $this->initializeSession($session);

        return $session;
    }

    private function getSessionBuilder(): SessionBuilder
    {
        if ($this->sessionBuilder === null) {
            $this->sessionBuilder = $this->createSessionBuilder($GLOBALS['pomm_db1']);
        }

        return $this->sessionBuilder;
    }

    /**
     * Instantiate a new SessionBuilder. This method is to be overloaded by each package to instantiate their own
     * SessionBuilder if any.
     *
     * @param array<string, mixed> $configuration
     * @return SessionBuilder
     */
    protected function createSessionBuilder(array $configuration): SessionBuilder
    {
        return new SessionBuilder($configuration);
    }

    /** If the test needs special poolers and/or client configuration, it goes here. */
    abstract protected function initializeSession(FoundationSession $session): void;
}

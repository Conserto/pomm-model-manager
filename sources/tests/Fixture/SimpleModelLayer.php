<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Test\Fixture;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\ModelManager\ModelLayer\ModelLayer;
use PommProject\ModelManager\Session;

/**
 * This class is NOT the right example of how ModelLayer is to be used. Good
 * practices are to handle complete transaction within a single method.
 * Transactions are split in several methods here to be tested properly.
 *
 * @copyright 2014 Grégoire HUBERT
 * @author Grégoire HUBERT
 * @license X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see ModelLayer
 */
class SimpleModelLayer extends ModelLayer
{

    public function startTransaction(): ModelLayer
    {
        return parent::startTransaction();
    }

    public function rollbackTransaction(?string $name = null): ModelLayer
    {
        return parent::rollbackTransaction($name);
    }

    public function setSavepoint(string $name): ModelLayer
    {
        return parent::setSavepoint($name);
    }

    public function releaseSavepoint(string $name): ModelLayer
    {
        return parent::releaseSavepoint($name);
    }

    public function commitTransaction(): ModelLayer
    {
        return parent::commitTransaction();
    }

    /**
     * @throws FoundationException
     */
    public function sendNotifyWithObserver(string $channel, string $data = ''): ?array
    {
        $observer = $this
            ->getSession()
            ->getObserver($channel)
            ->RestartListening()
        ;
        $this->sendNotify($channel, $data);
        usleep(300000);

        return $observer
            ->getNotification()
            ;
    }

    public function isInTransaction(): bool
    {
        return parent::isInTransaction();
    }

    public function isTransactionOk(): bool
    {
        return parent::isTransactionOk();
    }

    public function setDeferrable(array $keys, string $state): ModelLayer
    {
        return parent::setDeferrable($keys, $state);
    }

    public function setTransactionIsolationLevel(string $isolationLevel): ModelLayer
    {
        return parent::setTransactionIsolationLevel($isolationLevel);
    }

    public function setTransactionAccessMode(string $accessMode): ModelLayer
    {
        return parent::setTransactionAccessMode($accessMode);
    }

    public function executeAnonymousQuery(string $sql): ResultHandler
    {
        return parent::executeAnonymousQuery($sql);
    }

    public function getSession(): Session
    {
        return parent::getSession();
    }
}

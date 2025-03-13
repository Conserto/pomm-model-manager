<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\ModelLayer;

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Connection;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\ModelManager\Exception\ModelLayerException;
use PommProject\ModelManager\Model\Model;
use PommProject\ModelManager\Session;

/**
 * ModelLayer handles mechanisms around model method calls (transactions,
 * events etc.).
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         Client
 */
abstract class ModelLayer extends Client
{
    /** @see ClientInterface */
    public function getClientType(): string
    {
        return 'model_layer';
    }

    /** @see ClientInterface */
    public function getClientIdentifier(): string
    {
        return static::class;
    }

    /** @see ClientInterface */
    public function shutdown(): void
    {
    }

    /**
     * Start a new transaction.
     *
     * @throws FoundationException
     */
    protected function startTransaction(): ModelLayer
    {
        $this->executeAnonymousQuery('begin transaction');

        return $this;
    }

    /**
     * Set given constraints to deferred/immediate in the current transaction.
     * This applies to constraints being deferrable or deferred by default.
     * If the keys is an empty arrays, ALL keys will be set at the given state.
     * @see http://www.postgresql.org/docs/9.0/static/sql-set-constraints.html
     *
     * @throws  ModelLayerException|FoundationException if not valid state
     */
    protected function setDeferrable(array $keys, string $state): ModelLayer
    {
        if (empty($keys)) {
            $string = 'ALL';
        } else {
            $string = implode(
                ', ',
                array_map(
                    function ($key): string {
                        $parts = explode('.', $key);
                        $escapedParts = [];

                        foreach ($parts as $part) {
                            $escapedParts[] = $this->escapeIdentifier($part);
                        }

                        return implode('.', $escapedParts);
                    },
                    $keys
                )
            );
        }

        if (!in_array($state, [ Connection::CONSTRAINTS_DEFERRED, Connection::CONSTRAINTS_IMMEDIATE ])) {
            throw new ModelLayerException(
                sprintf(<<<EOMSG
'%s' is not a valid constraint modifier.
Use Connection::CONSTRAINTS_DEFERRED or Connection::CONSTRAINTS_IMMEDIATE.
EOMSG
,
                    $state
                )
            );
        }

        $this->executeAnonymousQuery(
            sprintf(
                "set constraints %s %s",
                $string,
                $state
            )
        );

        return $this;
    }

    /**
     * Transaction isolation level tells PostgreSQL how to manage with the
     * current transaction. The default is "READ COMMITTED".
     * @see http://www.postgresql.org/docs/9.0/static/sql-set-transaction.html
     *
     * @throws  ModelLayerException|FoundationException if not valid isolation level
     */
    protected function setTransactionIsolationLevel(string $isolationLevel): ModelLayer
    {
        $validIsolationLevels =
            [
                Connection::ISOLATION_READ_COMMITTED,
                Connection::ISOLATION_REPEATABLE_READ,
                Connection::ISOLATION_SERIALIZABLE
            ];

        if (!in_array(
            $isolationLevel,
            $validIsolationLevels
        )) {
            throw new ModelLayerException(
                sprintf(
                    "'%s' is not a valid transaction isolation level. Valid isolation levels are {%s} see Connection class constants.",
                    $isolationLevel,
                    implode(', ', $validIsolationLevels)
                )
            );
        }

        return $this->sendParameter(
            "set transaction isolation level %s",
            $isolationLevel
        );
    }

    /**
     * Transaction access modes tell PostgreSQL if transaction are able to write or read only.
     * @see http://www.postgresql.org/docs/9.0/static/sql-set-transaction.html
     *
     * @throws  ModelLayerException|FoundationException if not valid access mode
     */
    protected function setTransactionAccessMode(string $accessMode): ModelLayer
    {
        $validAccessModes = [Connection::ACCESS_MODE_READ_ONLY, Connection::ACCESS_MODE_READ_WRITE];

        if (!in_array($accessMode, $validAccessModes)) {
            throw new ModelLayerException(
                sprintf(
                    "'%s' is not a valid transaction access mode. Valid access modes are {%s}, see Connection class constants.",
                    $accessMode,
                    implode(', ', $validAccessModes)
                )
            );
        }

        return $this->sendParameter("set transaction %s", $accessMode);
    }

    /**
     * Set a savepoint in a transaction.
     *
     * @throws FoundationException
     */
    protected function setSavepoint(string $name): ModelLayer
    {
        return $this->sendParameter("savepoint %s", $this->escapeIdentifier($name));
    }

    /**
     * Drop a savepoint.
     *
     * @throws FoundationException
     */
    protected function releaseSavepoint(string $name): ModelLayer
    {
        return $this->sendParameter("release savepoint %s", $this->escapeIdentifier($name));
    }

    /**
     * Rollback a transaction. If a name is specified, the transaction is rollback to the given savepoint.
     * Otherwise, the whole transaction is rollback.
     *
     * @throws FoundationException
     */
    protected function rollbackTransaction(?string $name = null): ModelLayer
    {
        $sql = "rollback transaction";
        if ($name !== null) {
            $sql = sprintf("rollback to savepoint %s", $this->escapeIdentifier($name));
        }

        $this->executeAnonymousQuery($sql);

        return $this;
    }

    /**
     * Commit a transaction.
     *
     * @throws FoundationException
     */
    protected function commitTransaction(): ModelLayer
    {
        $this->executeAnonymousQuery('commit transaction');

        return $this;
    }

    /**
     * Tell if a transaction is open or not.
     *
     * @throws FoundationException
     * @see    Cient
     */
    protected function isInTransaction(): bool
    {
        $status = $this
            ->getSession()
            ->getConnection()
            ->getTransactionStatus();

        return in_array(
            $status,
            [\PGSQL_TRANSACTION_INTRANS, \PGSQL_TRANSACTION_INERROR, \PGSQL_TRANSACTION_ACTIVE],
            true
        );
    }

    /**
     * In PostgreSQL, an error during a transaction cancels all the queries and rollback the transaction on commit.
     * This method returns the current transaction's status. If no transactions are open, it returns null.
     *
     * @throws FoundationException
     */
    protected function isTransactionOk(): ?bool
    {
        if (!$this->isInTransaction()) {
            return null;
        }

        $status = $this
            ->getSession()
            ->getConnection()
            ->getTransactionStatus();

        return $status === \PGSQL_TRANSACTION_INTRANS;
    }

    /**
     * Send a NOTIFY event to the database server. An optional data can be sent with the notification.
     *
     * @throws FoundationException
     */
    protected function sendNotify(string $channel, string $data = ''): ModelLayer
    {
        return $this->sendParameter('notify %s, %s', $channel, $this->escapeLiteral($data));
    }

    /**
     * Proxy to Connection::executeAnonymousQuery()
     *
     * @throws FoundationException
     */
    protected function executeAnonymousQuery(string $sql): ResultHandler|array
    {
        return $this->getSession()
            ->getConnection()
            ->executeAnonymousQuery($sql);
    }

    /**
     * Proxy to Connection::escapeIdentifier()
     *
     * @throws FoundationException
     */
    protected function escapeIdentifier(string $string): string
    {
        return $this
            ->getSession()
            ->getConnection()
            ->escapeIdentifier($string);
    }

    /**
     * Proxy to Connection::escapeLiteral()
     *
     * @throws FoundationException
     */
    protected function escapeLiteral(string $string): string
    {
        return $this
            ->getSession()
            ->getConnection()
            ->escapeLiteral($string);
    }

    /**
     * Proxy to Session::getModel();
     *
     * @template TModel of Model
     * @param class-string<TModel> $identifier
     * @return TModel
     * @throws FoundationException
     */
    protected function getModel(string $identifier): Model
    {
        /** @var Model $modelManager */
        $modelManager = $this
            ->getSession()
            ->getClientUsingPooler('model', $identifier);

        return $modelManager;
    }

    /**
     * Send a parameter to the server.
     * The parameter MUST have been properly checked and escaped if needed as it is going to be passed AS IS to the
     * server. Sending untrusted parameters may lead to potential SQL injection.
     *
     * @throws FoundationException
     */
    private function sendParameter(string $sql, string $identifier, ?string $parameter = null): ModelLayer
    {
        $this->executeAnonymousQuery(sprintf($sql, $identifier, $parameter));

        return $this;
    }

    /**
     * All subclasses of Client have to use this method to access the session.
     *
     * @throws FoundationException if Session is not set.
     */
    protected function getSession(): Session
    {
        /** @var Session $session */
        $session = parent::getSession();

        return $session;
    }
}

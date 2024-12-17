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

use PommProject\Foundation\Client\Client;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;

class ModelSchemaClient extends Client
{
    public function getClientType(): string
    {
        return 'test';
    }

    public function getClientIdentifier(): string
    {
        return 'complex_fixture';
    }

    public function initialize(Session $session): void
    {
        parent::initialize($session);

        $this->createSchema();
    }

    public function shutdown(): void
    {
        $this->dropSchema();
    }

    /**
     * @throws SqlException
     */
    public function createSchema(): static
    {
        $sql =
            [
                "drop schema if exists pomm_test cascade",
                "begin",
                "create schema pomm_test",
                "create type pomm_test.complex_number as (real float8, imaginary float8)",
                "commit",
            ];

        try {
            foreach ($sql as $stmt) {
                $this->executeSql($stmt);
            }
        } catch (SqlException $e) {
            $this->executeSql('rollback');
            throw $e;
        }

        return $this;
    }

    public function dropSchema(): static
    {
        $sql = "drop schema if exists pomm_test cascade";
        $this->executeSql($sql);

        return $this;
    }

    /**
     * @throws SqlException
     * @throws FoundationException
     * @throws ConnectionException
     */
    protected function executeSql(string $sql): ResultHandler|array
    {
        return $this
            ->getSession()
            ->getConnection()
            ->executeAnonymousQuery($sql)
            ;
    }
}

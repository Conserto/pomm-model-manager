<?php

namespace PommProject\ModelManager\Test\Fixture;

use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Model\Model;

class ConverterFixtureModel extends Model
{
    public function __construct()
    {
        $this->structure = new ConverterFixtureStructure();
        $this->flexibleEntityClass = ConverterFixture::class;
    }

    public function initialize(Session $session): void
    {
        parent::initialize($session);
        $this->dropTable();
        $this->createTable();
    }

    public function shutdown(): void
    {
        $this->dropTable();
    }

    protected function createTable(): static
    {
        $sql = <<<SQL
            create temporary table linked_fixture (
                id int4,
                label varchar(50),
                created_at timestamp not null default now(),
                primary key (id)
            )
        SQL;
        $this->executeAnonymousQuery($sql);

        $sql = <<<SQL
            insert into linked_fixture (id, label) values
            (
                2,
                'linked_fixture_test'
            )
        SQL;
        $this->executeAnonymousQuery($sql);

        $sql = <<<SQL
            create temporary table %s (
                id int4,
                label varchar(50),
                id_linked_fixture int4,
                created_at timestamp not null default now(),
                primary key (id)
            )
        SQL;
        $this->executeAnonymousQuery(sprintf($sql, $this->getStructure()->getRelation()));

        $sql = <<<SQL
            insert into %s (id, label, id_linked_fixture) values
            (
                1,
                'converter_fixture_test',
                2
            )
        SQL;
        $this->executeAnonymousQuery(sprintf($sql, $this->getStructure()->getRelation()));

        return $this;
    }

    protected function dropTable(): static
    {
        $this->executeAnonymousQuery("drop table if exists linked_fixture");
        $this
            ->executeAnonymousQuery(
                sprintf(
                    "drop table if exists %s",
                    $this->getStructure()->getRelation()
                )
            )
        ;

        return $this;
    }

    public function findOneWithLinkedFixture()
    {
        $sql = <<<SQL
            SELECT :projection
            FROM converter_fixture cf
            JOIN linked_fixture lf ON lf.id = cf.id_linked_fixture
            LIMIT 1;
        SQL;

        $projection = $this->createProjection()
            ->setField('linked_fixture', 'lf', LinkedFixture::class);

        $sql = strtr($sql, [
            ':projection' => $projection->formatFieldsWithFieldAlias('cf'),
        ]);

        return $this->query($sql, projection: $projection)->current();
    }
}

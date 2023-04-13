<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model\ModelTrait;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Pager;
use PommProject\Foundation\PreparedQuery\PreparedQueryManager;
use PommProject\Foundation\Where;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\CollectionIterator;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;
use PommProject\ModelManager\Model\Model;
use PommProject\ModelManager\Model\Projection;
use PommProject\ModelManager\Model\RowStructure;

/**
 * Basic read queries for model instances.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
trait ReadQueries
{
    use BaseTrait;

    /** @see Model */
    abstract protected function escapeIdentifier(string $string): string;

    /** @see Model */
    abstract public function getStructure(): RowStructure;

    /**
     * Return all elements from a relation. If a suffix is given, it is append
     * to the query. This is mainly useful for "order by" statements.
     * NOTE: suffix is inserted as is with NO ESCAPING. DO NOT use it to place
     * "where" condition nor any untrusted params.
     */
    public function findAll(?string $suffix = null): CollectionIterator
    {
        $sql = strtr(
            "select :fields from :table :suffix",
            [
                ':fields' => $this->createProjection()->formatFieldsWithFieldAlias(),
                ':table'  => $this->getStructure()->getRelation(),
                ':suffix' => $suffix,
            ]
        );

        return $this->query($sql);
    }

    /**
     * Perform a simple select on a given condition
     * NOTE: suffix is inserted as is with NO ESCAPING. DO NOT use it to place
     * "where" condition nor any untrusted params.
     */
    public function findWhere(string|Where $where, array $values = [], string $suffix = ''): CollectionIterator
    {
        if (!$where instanceof Where) {
            $where = new Where($where, $values);
        }

        return $this->query($this->getFindWhereSql($where, $this->createProjection(), $suffix), $where->getValues());
    }

    /**
     * Return an entity upon its primary key. If no entities are found, null is returned.
     *
     * @throws ModelException
     */
    public function findByPK(array $primaryKey): ?FlexibleEntityInterface
    {
        $where = $this
            ->checkPrimaryKey($primaryKey)
            ->getWhereFrom($primaryKey);

        $iterator = $this->findWhere($where);

        return $iterator->isEmpty() ? null : $iterator->current();
    }

    /**
     * Return the number of records matching a condition.
     *
     * @throws FoundationException
     */
    public function countWhere(string|Where $where, array $values = []): int
    {
        $sql = sprintf(
            "select count(*) as result from %s where :condition",
            $this->getStructure()->getRelation()
        );

        return $this->fetchSingleValue($sql, $where, $values);
    }

    /**
     * Check if rows matching the given condition do exist or not.
     *
     * @throws FoundationException
     */
    public function existWhere(string|Where $where, array $values = []): bool
    {
        $sql = sprintf(
            "select exists (select true from %s where :condition) as result",
            $this->getStructure()->getRelation()
        );

        return $this->fetchSingleValue($sql, $where, $values);
    }

    /**
     * Fetch a single value named « result » from a query.
     * The query must be formatted with ":condition" as WHERE condition
     * placeholder. If the $where argument is a string, it is turned into a
     * Where instance.
     *
     * @throws FoundationException
     */
    protected function fetchSingleValue(string $sql, string|Where $where, array $values): mixed
    {
        if (!$where instanceof Where) {
            $where = new Where($where, $values);
        }

        $sql = str_replace(":condition", (string) $where, $sql);

        return $this
            ->getSession()
            ->getClientUsingPooler('query_manager', PreparedQueryManager::class)
            ->query($sql, $where->getValues())
            ->current()['result']
            ;
    }

    /**
     * Paginate a query.
     *
     * @throws FoundationException
     */
    public function paginateFindWhere(Where $where, int $itemPerPage, int $page = 1, string $suffix = ''): Pager
    {
        $projection = $this->createProjection();

        return $this->paginateQuery(
            $this->getFindWhereSql($where, $projection, $suffix),
            $where->getValues(),
            $this->countWhere($where),
            $itemPerPage,
            $page,
            $projection
        );
    }

    /**
     * Paginate a SQL query.
     * It is important to note it adds limit and offset at the end of the given
     * query.
     */
    protected function paginateQuery(
        string $sql,
        array $values,
        int $count,
        int $itemPerPage,
        int $page = 1,
        Projection $projection = null
    ): Pager {
        if ($page < 1) {
            throw new \InvalidArgumentException(
                sprintf("Page cannot be < 1. (%d given)", $page)
            );
        }

        if ($itemPerPage <= 0) {
            throw new \InvalidArgumentException(
                sprintf("'item_per_page' must be strictly positive (%d given).", $itemPerPage)
            );
        }

        $offset = $itemPerPage * ($page - 1);
        $limit  = $itemPerPage;

        return new Pager(
            $this->query(
                sprintf("%s offset %d limit %d", $sql, $offset, $limit),
                $values,
                $projection
            ),
            $count,
            $itemPerPage,
            $page
        );
    }

    /** This is the standard SQL query to fetch instances from the current relation. */
    protected function getFindWhereSql(Where $where, Projection $projection, string $suffix = ''): string
    {
        return strtr(
            'select :projection from :relation where :condition :suffix',
            [
                ':projection' => $projection->formatFieldsWithFieldAlias(),
                ':relation'   => $this->getStructure()->getRelation(),
                ':condition'  => (string) $where,
                ':suffix'     => $suffix,
            ]
        );
    }

    /** Check if model has a primary key */
    protected function hasPrimaryKey(): bool
    {
        $primaryKeys = $this->getStructure()->getPrimaryKey();

        return !empty($primaryKeys);
    }

    /**
     * Check if the given values fully describe a primary key. Throw a ModelException if not.
     *
     * @throws ModelException
     */
    protected function checkPrimaryKey(array $values): Model
    {
        if (!$this->hasPrimaryKey()) {
            throw new ModelException(
                sprintf(
                    "Attached structure '%s' has no primary key.",
                    $this->getStructure()::class
                )
            );
        }

        foreach ($this->getStructure()->getPrimaryKey() as $key) {
            if (!isset($values[$key])) {
                throw new ModelException(
                    sprintf(
                        "Key '%s' is missing to fully describes the primary key {%s}.",
                        $key,
                        join(', ', $this->getStructure()->getPrimaryKey())
                    )
                );
            }
        }

        return $this;
    }

    /**
     * Build a condition on given values.
     *
     * @throws ModelException
     */
    protected function getWhereFrom(array $values): Where
    {
        $where = new Where();

        foreach ($values as $field => $value) {
            $where->andWhere(
                sprintf(
                    "%s = $*::%s",
                    $this->escapeIdentifier($field),
                    $this->getStructure()->getTypeFor($field)
                ),
                [$value]
            );
        }

        return $where;
    }
}

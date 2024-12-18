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

use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\Where;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\CollectionIterator;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Basic write queries for model instances.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @template T of FlexibleEntityInterface
 */
trait WriteQueries
{
    /** @use ReadQueries<T> */
    use ReadQueries;

    /**
     * Insert a new entity in the database. The entity is passed by reference.
     * It is updated with values returned by the database (ie, default values).
     *
     * @param-out T $entity
     * @throws ModelException|SqlException
     */
    public function insertOne(FlexibleEntityInterface &$entity): self
    {
        $values = $entity->fields(
            array_intersect(
                array_keys($this->getStructure()->getDefinition()),
                array_keys($entity->fields())
            )
        );
        $sql = strtr(
            "insert into :relation (:fields) values (:values) returning :projection",
            [
                ':relation' => $this->getStructure()->getRelation(),
                ':fields' => $this->getEscapedFieldList(array_keys($values)),
                ':projection' => $this->createProjection()->formatFieldsWithFieldAlias(),
                ':values' => implode(',', $this->getParametersList($values))
            ]);

        $entity = $this
            ->query($sql, array_values($values))
            ->current()
            ->status(FlexibleEntityInterface::STATUS_EXIST);

        return $this;
    }

    /**
     * Update the entity. ONLY the fields indicated in the $fields array are
     * updated. The entity is passed by reference and its values are updated
     * with the values from the database. This means all changes not updated
     * are lost. The update is made upon a condition on the primary key. If the
     * primary key is not fully set, an exception is thrown.
     *
     * @param-out T $entity
     * @throws ModelException|SqlException
     */
    public function updateOne(FlexibleEntityInterface &$entity, array $fields = []): self
    {
        if (empty($fields)) {
            $fields = $entity->getModifiedColumns();
        }

        $entity = $this->updateByPk(
            $entity->fields($this->getStructure()->getPrimaryKey()),
            $entity->fields($fields)
        );

        return $this;
    }

    /**
     * Update a record and fetch it with its new values. If no records match
     * the given key, null is returned.
     *
     * @return ?T
     * @throws ModelException|SqlException
     */
    public function updateByPk(array $primaryKey, array $updates): ?FlexibleEntityInterface
    {
        $where = $this
            ->checkPrimaryKey($primaryKey)
            ->getWhereFrom($primaryKey);

        return $this->updateWhere($where, $updates)->current();
    }

    /**
     * Update records according to where condition and fetch them with their new values.
     *
     * @return CollectionIterator<T>
     * @throws ModelException|SqlException
     */
    public function updateWhere(Where $where, array $updates): CollectionIterator
    {
        $parameters = $this->getParametersList($updates);
        $updateStrings = [];

        foreach ($updates as $field_name => $new_value) {
            $updateStrings[] = sprintf(
                "%s = %s",
                $this->escapeIdentifier($field_name),
                $parameters[$field_name]
            );
        }

        $sql = strtr(
            "update :relation set :update where :condition returning :projection",
            [
                ':relation' => $this->getStructure()->getRelation(),
                ':update' => implode(', ', $updateStrings),
                ':condition' => (string)$where,
                ':projection' => $this->createProjection()->formatFieldsWithFieldAlias(),
            ]
        );

        $iterator = $this->query($sql, array_merge(array_values($updates), $where->getValues()));

        foreach ($iterator as $item) {
            $item->status(FlexibleEntityInterface::STATUS_EXIST);
        }

        $iterator->rewind();

        return $iterator;
    }

    /**
     * Delete an entity from a table. Entity is passed by reference and is
     * updated with the values fetched from the deleted record.
     *
     * @param-out ?T $entity
     * @throws ModelException|SqlException
     */
    public function deleteOne(FlexibleEntityInterface &$entity): self
    {
        $entity = $this->deleteByPK($entity->fields($this->getStructure()->getPrimaryKey()));

        return $this;
    }

    /**
     * Delete a record from its primary key. The deleted entity is returned or null if not found.
     *
     * @return ?T
     * @throws ModelException|SqlException
     */
    public function deleteByPK(array $primaryKey): ?FlexibleEntityInterface
    {
        $where = $this
            ->checkPrimaryKey($primaryKey)
            ->getWhereFrom($primaryKey);

        return $this->deleteWhere($where)->current();
    }

    /**
     * Delete records by a given condition. A collection of all deleted entries is returned.
     *
     * @param string|Where $where
     * @param array $values
     * @return CollectionIterator<T>
     * @throws SqlException
     */
    public function deleteWhere(string|Where $where, array $values = []): CollectionIterator
    {
        if (!$where instanceof Where) {
            $where = new Where($where, $values);
        }

        $sql = strtr(
            "delete from :relation where :condition returning :projection",
            [
                ':relation' => $this->getStructure()->getRelation(),
                ':condition' => (string)$where,
                ':projection' => $this->createProjection()->formatFieldsWithFieldAlias(),
            ]
        );

        $collection = $this->query($sql, $where->getValues());
        foreach ($collection as $entity) {
            $entity->status(FlexibleEntityInterface::STATUS_NONE);
        }
        $collection->rewind();

        return $collection;
    }

    /**
     * Create a new entity from given values and save it in the database.
     *
     * @return T
     * @throws ModelException|SqlException
     */
    public function createAndSave(array $values): FlexibleEntityInterface
    {
        $entity = $this->createEntity($values);
        $this->insertOne($entity);

        return $entity;
    }

    /** Return a comma separated list with the given escaped field names. */
    public function getEscapedFieldList(array $fields): string
    {
        return implode(
            ', ',
            array_map(
                fn($field) => $this->escapeIdentifier($field),
                $fields
            ));
    }

    /**
     * Create a parameters list from values.
     * @return array<mixed, string>
     * @throws ModelException
     */
    protected function getParametersList(array $values): array
    {
        $parameters = [];

        foreach ($values as $name => $value) {
            $parameters[$name] = sprintf(
                "$*::%s",
                $this->getStructure()->getTypeFor($name)
            );
        }

        return $parameters;
    }
}

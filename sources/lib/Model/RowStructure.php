<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model;

use PommProject\ModelManager\Exception\ModelException;

/**
 * Represent a composite structure like table or row.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class RowStructure implements \ArrayAccess
{
    protected array $primaryKey       = [];
    protected array $fieldDefinitions = [];
    protected string $relation;

    /** Add a complete definition. */
    public function setDefinition(array $definition): RowStructure
    {
        $this->fieldDefinitions = $definition;

        return $this;
    }

    /** Add inherited structure. */
    public function inherits(RowStructure $structure): RowStructure
    {
        foreach ($structure->getDefinition() as $field => $type) {
            $this->addField($field, $type);
        }

        return $this;
    }

    /** Set or change the relation.*/
    public function setRelation(string $relation): RowStructure
    {
        $this->relation = $relation;

        return $this;
    }

    /** Set or change the primary key definition. */
    public function setPrimaryKey(array $primaryKey): RowStructure
    {
        $this->primaryKey = $primaryKey;

        return $this;
    }

    /** Add a new field structure. */
    public function addField(string $name, string $type): RowStructure
    {
        $this->checkNotNull($type, 'type')
            ->checkNotNull($name, 'name')
            ->fieldDefinitions[$name] = $type;

        return $this;
    }

    /** Return an array of all field names */
    public function getFieldNames(): array
    {
        return array_keys($this->fieldDefinitions);
    }

    /** Check if a field exist in the structure */
    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->checkNotNull($name, 'name')->fieldDefinitions);
    }

    /**
     * Return the type associated with the field
     *
     * @throws ModelException if $name is null or name does not exist.
     */
    public function getTypeFor(string $name): string
    {
        return $this->checkExist($name)->fieldDefinitions[$name];
    }

    /** Return all fields and types */
    public function getDefinition(): array
    {
        return $this->fieldDefinitions;
    }

    /** Return the relation name. */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /** Return the primary key definition. */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    /** Test if given value is null. */
    private function checkNotNull(?string $val, string $name): RowStructure
    {
        if ($val === null) {
            throw new \InvalidArgumentException(sprintf("'%s' cannot be null in '%s'.", $name, static::class));
        }

        return $this;
    }

    /**
     * Test if a field exist.
     *
     * @throws ModelException if $name does not exist.
     */
    private function checkExist(string $name): RowStructure
    {
        if (!$this->hasField($name)) {
            throw new ModelException(
                sprintf(
                    "Field '%s' is not defined in structure '%s'. Defined fields are {%s}",
                    $name,
                    static::class,
                    implode(', ', array_keys($this->fieldDefinitions))
                )
            );
        }

        return $this;
    }

    /** @see \ArrayAccess */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->addField($offset, $value);
    }

    /**
     * @throws ModelException
     * @see \ArrayAccess
     */
    public function offsetGet(mixed $offset): string
    {
        return $this->getTypeFor($offset);
    }

    /** @see \ArrayAccess */
    public function offsetExists(mixed $offset): bool
    {
        return $this->hasField($offset);
    }

    /**
     * @throws ModelException
     * @see \ArrayAccess
     */
    public function offsetUnset(mixed $offset): void
    {
        throw new ModelException(sprintf("Cannot unset a structure field ('%s').", $offset));
    }
}

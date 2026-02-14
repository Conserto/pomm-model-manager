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
 * @implements \ArrayAccess<string, string>
 */
class RowStructure implements \ArrayAccess
{
    /** @var string[] */
    protected array $primaryKey       = [];
    /** @var array<string, string> */
    protected array $fieldDefinitions = [];
    protected string $relation;

    /**
     * Add a complete definition.
     * @param array<string, string> $definition
     * @return $this
     */
    public function setDefinition(array $definition): static
    {
        $this->fieldDefinitions = $definition;

        return $this;
    }

    /** Add inherited structure. */
    public function inherits(RowStructure $structure): static
    {
        foreach ($structure->getDefinition() as $field => $type) {
            $this->addField($field, $type);
        }

        return $this;
    }

    /** Set or change the relation.*/
    public function setRelation(string $relation): static
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * Set or change the primary key definition.
     * @param string[] $primaryKey
     * @return $this
     */
    public function setPrimaryKey(array $primaryKey): static
    {
        $this->primaryKey = $primaryKey;

        return $this;
    }

    /** Add a new field structure. */
    public function addField(string $name, string $type): static
    {
        $this->checkNotNull($type, 'type')
            ->checkNotNull($name, 'name')
            ->fieldDefinitions[$name] = $type;

        return $this;
    }

    /**
     * Return an array of all field names
     * @return string[]
     */
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

    /**
     * Return all fields and types
     * @return array<string, string>
     */
    public function getDefinition(): array
    {
        return $this->fieldDefinitions;
    }

    /** Return the relation name. */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * Return the primary key definition.
     * @return string[]
     */
    public function getPrimaryKey(): array
    {
        return $this->primaryKey;
    }

    /** Test if given value is null. */
    private function checkNotNull(?string $val, string $name): static
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
    private function checkExist(string $name): static
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
        if (!is_string($offset)) {
            throw new \InvalidArgumentException(sprintf(
                "Offset must be a string in '%s'. %s given.",
                static::class,
                gettype($offset)
            ));
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf(
                "Value must be a string in '%s'. %s given.",
                static::class,
                gettype($value)
            ));
        }
        $this->addField($offset, $value);
    }

    /**
     * @throws ModelException
     * @see \ArrayAccess
     */
    public function offsetGet(mixed $offset): string
    {
        if (!is_string($offset)) {
            throw new \InvalidArgumentException(sprintf(
                "Offset must be a string in '%s'. %s given.",
                static::class,
                gettype($offset)
            ));
        }

        return $this->getTypeFor($offset);
    }

    /** @see \ArrayAccess */
    public function offsetExists(mixed $offset): bool
    {
        if (!is_string($offset)) {
            // Non-string offsets are not supported for this ArrayAccess<string, string>
            return false;
        }

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

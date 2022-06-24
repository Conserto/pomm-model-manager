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
 * RowStructure
 *
 * Represent a composite structure like table or row.
 *
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class RowStructure implements \ArrayAccess
{
    protected array $primary_key       = [];
    protected array $field_definitions = [];
    protected string $relation;

    /**
     * setDefinition
     *
     * Add a complete definition.
     *
     * @access public
     * @param  array        $definition
     * @return RowStructure $this
     */
    public function setDefinition(array $definition): RowStructure
    {
        $this->field_definitions = $definition;

        return $this;
    }

    /**
     * inherits
     *
     * Add inherited structure.
     *
     * @access public
     * @param RowStructure $structure
     * @return RowStructure $this
     */
    public function inherits(RowStructure $structure): RowStructure
    {
        foreach ($structure->getDefinition() as $field => $type) {
            $this->addField($field, $type);
        }

        return $this;
    }

    /**
     * setRelation
     *
     * Set or change the relation.
     *
     * @access public
     * @param string $relation
     * @return RowStructure $this
     */
    public function setRelation(string $relation): RowStructure
    {
        $this->relation = $relation;

        return $this;
    }

    /**
     * setPrimaryKey
     *
     * Set or change the primary key definition.
     *
     * @access public
     * @param  array        $primary_key
     * @return RowStructure $this
     */
    public function setPrimaryKey(array $primary_key): RowStructure
    {
        $this->primary_key = $primary_key;

        return $this;
    }

    /**
     * addField
     *
     * Add a new field structure.
     *
     * @access public
     * @param string $name
     * @param string $type
     * @return RowStructure $this
     */
    public function addField(string $name, string $type): RowStructure
    {
        $this->checkNotNull($type, 'type')
            ->checkNotNull($name, 'name')
            ->field_definitions[$name] = $type;

        return $this;
    }

    /**
     * getFieldNames
     *
     * Return an array of all field names
     *
     * @access public
     * @return array
     */
    public function getFieldNames(): array
    {
        return array_keys($this->field_definitions);
    }

    /**
     * hasField
     *
     * Check if a field exist in the structure
     *
     * @access public
     * @param string $name
     * @return bool
     */
    public function hasField(string $name): bool
    {
        return array_key_exists($name, $this->checkNotNull($name, 'name')->field_definitions);
    }

    /**
     * getTypeFor
     *
     * Return the type associated with the field
     *
     * @access public
     * @param string $name
     * @return string $type
     *@throws ModelException if $name is null or name does not exist.
     */
    public function getTypeFor(string $name): string
    {
        return $this->checkExist($name)->field_definitions[$name];
    }

    /**
     * getDefinition
     *
     * Return all fields and types
     *
     * @return array
     */
    public function getDefinition(): array
    {
        return $this->field_definitions;
    }

    /**
     * getRelation
     *
     * Return the relation name.
     *
     * @access public
     * @return string
     */
    public function getRelation(): string
    {
        return $this->relation;
    }

    /**
     * getPrimaryKey
     *
     * Return the primary key definition.
     *
     * @access public
     * @return array
     */
    public function getPrimaryKey(): array
    {
        return $this->primary_key;
    }

    /**
     * checkNotNull
     *
     * Test if given value is null.
     *
     * @access              private
     * @param string|null $val
     * @param string $name
     * @return RowStructure $this
     */
    private function checkNotNull(?string $val, string $name): RowStructure
    {
        if ($val === null) {
            throw new \InvalidArgumentException(sprintf("'%s' cannot be null in '%s'.", $name, $this::class));
        }

        return $this;
    }

    /**
     * checkExist
     *
     * Test if a field exist.
     *
     * @access private
     * @param string $name
     * @return RowStructure $this
     *@throws ModelException if $name does not exist.
     */
    private function checkExist(string $name): RowStructure
    {
        if (!$this->hasField($name)) {
            throw new ModelException(
                sprintf(
                    "Field '%s' is not defined in structure '%s'. Defined fields are {%s}",
                    $name,
                    $this::class,
                    join(', ', array_keys($this->field_definitions))
                )
            );
        }

        return $this;
    }

    /**
     * @see \ArrayAccess
     */
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

    /**
     * @see \ArrayAccess
     */
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

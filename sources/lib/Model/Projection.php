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
 * Define the content of SELECT or RETURNING (projection) statements.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class Projection implements \IteratorAggregate, \Stringable
{
    protected array $fields = [];
    protected array $types = [];

    /**
     * @param string $flexibleEntityClass
     * @param array|null $structure list of field names with types.
     */
    public function __construct(protected string $flexibleEntityClass, ?array $structure = null)
    {
        if ($structure != null) {
            foreach ($structure as $field_name => $type) {
                $this->setField($field_name, sprintf("%%:%s:%%", $field_name), $type);
            }
        }
    }

    /**
     * This returns an ArrayIterator using the name => type association of the projection.

     * @see IteratorAggregate
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->types);
    }

    /** Get the flexible entity class associated with this projection. */
    public function getFlexibleEntityClass(): string
    {
        return $this->flexibleEntityClass;
    }

    /**
     * Set a field with a content. This override previous definition if exist.
     *
     * @throws \InvalidArgumentException if $name or $content is null
     */
    public function setField(string $name, string $content, ?string $type = null): Projection
    {
        $this->checkField($name)->fields[$name] = $content;
        $this->types[$name] = $type;

        return $this;
    }

    /**
     * Set or override a field type definition.
     *
     * @throws ModelException if name is null or does not exist.
     */
    public function setFieldType(string $name, ?string $type): Projection
    {
        $this->checkFieldExist($name)->types[$name] = $type;

        return $this;
    }

    /**
     * Unset an existing field
     *
     * @throws ModelException if field $name does not exist.
     */
    public function unsetField(string $name): Projection
    {
        $this->checkFieldExist($name);
        unset($this->fields[$name], $this->types[$name]);

        return $this;
    }

    /**
     * Unset multiple existing fields
     *
     * @throws ModelException if one field of $fields does not exist.
     */
    public function unsetFields(array $fields): Projection
    {
        foreach ($fields as $field) {
            $this->unsetField($field);
        }

        return $this;
    }

    /** Return if the given field exist. */
    public function hasField(string $name): bool
    {
        return isset($this->checkField($name)->fields[$name]);
    }

    /**
     * Return the type associated with the given field.
     *
     * @param string $name
     * @return string|null null if type is not set
     * @throws ModelException if $name is null or field does not exist
     */
    public function getFieldType(string $name): ?string
    {
        return $this->checkFieldExist($name)->types[$name] != null
            ? rtrim((string) $this->types[$name], '[]')
            : null;
    }

    /**
     * Tel if a field is an array.
     *
     * @throws \InvalidArgumentException if $name is null
     * @throws ModelException if $name does not exist.
     */
    public function isArray(string $name): bool
    {
        return (bool)preg_match('/\[\]$/', (string)$this->checkFieldExist($name)->types[$name]);
    }

    /**
     * Return fields names list.
     *
     * @return array fields list
     */
    public function getFieldNames(): array
    {
        return array_keys($this->fields);
    }

    /** Return an array with the known types. */
    public function getFieldTypes(): array
    {
        $fields = [];
        foreach ($this->fields as $name => $value) {
            $fields[$name] = $this->types[$name] ?? null;
        }

        return $fields;
    }

    /**
     * Prepend the field name with alias if given.
     *
     * @throws \InvalidArgumentException if $name is null
     * @throws ModelException if $name does not exist.
     */
    public function getFieldWithTableAlias(string $name, ?string $tableAlias = null): string
    {
        $replace = $tableAlias === null ? '' : sprintf("%s.", $tableAlias);

        return $this->replaceToken($this->checkFieldExist($name)->fields[$name], $replace);
    }

    /** Return the array of fields with table aliases expanded. */
    public function getFieldsWithTableAlias(?string $tableAlias = null): array
    {
        $vals = [];
        $replace = $tableAlias === null ? '' : sprintf("%s.", $tableAlias);

        foreach ($this->fields as $name => $definition) {
            $vals[$name] = $this->replaceToken($this->fields[$name], $replace);
        }

        return $vals;
    }

    /** Return a formatted string with fields like a.field1, a.field2, ..., a.fieldN */
    public function formatFields(?string $tableAlias = null): string
    {
        return implode(', ', $this->getFieldsWithTableAlias($tableAlias));
    }

    /** Return a formatted string with fields like a.field1 AS field1, a.field2 AS fields2, ... */
    public function formatFieldsWithFieldAlias(?string $tableAlias = null): string
    {
        $fields = $this->getFieldsWithTableAlias($tableAlias);

        return implode(
            ', ',
            array_map(
                fn($fieldAlias, $fieldDefinition): string => sprintf(
                    '%s as "%s"',
                    $fieldDefinition,
                    addcslashes((string) $fieldAlias, '"\\')
                ),
                array_keys($fields),
                $fields
            )
        );
    }

    /** String representation = formatFieldsWithFieldAlias(). */
    public function __toString(): string
    {
        return $this->formatFieldsWithFieldAlias();
    }

    /** Check if $name is not null */
    private function checkField(?string $name): Projection
    {
        if ($name === null) {
            throw new \InvalidArgumentException("Field name cannot be null.");
        }

        return $this;
    }

    /**
     * Check if a field exist.
     *
     * @throws ModelException if field does not exist
     */
    private function checkFieldExist(string $name): Projection
    {
        if (!$this->checkField($name)->hasField($name)) {
            throw new ModelException(sprintf(
                "Field '%s' does not exist. Available fields are {%s}.",
                $name,
                implode(', ', $this->getFieldNames()
                )
            ));
        }

        return $this;
    }

    /**
     * Replace placeholders with their quoted names.
     *
     * @param string $string field definition
     * @param string $prefix optional unquoted prefix
     * @return string
     */
    protected function replaceToken(string $string, string $prefix = ''): string
    {
        return preg_replace_callback(
            '/%:(\w.*):%/U',
            fn(array $matches): string => sprintf('%s"%s"', $prefix, addcslashes((string) $matches[1], '"\\')),
            $string
        );
    }
}

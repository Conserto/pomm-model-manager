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

use PommProject\Foundation\Inflector;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleContainer;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Parent for entity classes.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 */
abstract class FlexibleEntity extends FlexibleContainer implements \ArrayAccess
{
    public static bool $strict = true;
    protected static ?array $hasMethods = null;

    /**
     * Instantiate the entity and hydrate it with the given values.
     *
     * @param array|null $values Optional starting values.
     */
    public function __construct(array $values = null)
    {
        if ($values !== null) {
            $this->hydrate($values);
        }
    }

    /**
     * Returns the $var value
     *
     * @param string|array $var Key(s) you want to retrieve value from.
     * @return mixed
     * @throws  ModelException if strict and the attribute does not exist.
     */
    final public function get(string|array $var): mixed
    {
        if (is_array($var)) {
            return array_intersect_key($this->container, array_flip($var));
        } elseif ($this->has($var)) {
            return $this->container[$var];
        } elseif (static::$strict === true) {
            throw new ModelException(sprintf("No such key '%s'.", $var));
        }

        return null;
    }

    /** Returns true if the given key exists. */
    final public function has(string $var): bool
    {
        return isset($this->container[$var]) || array_key_exists($var, $this->container);
    }

    /**
     * Set a value in the var holder.
     *
     * @param string $var Attribute name.
     * @param mixed $value Attribute value.
     */
    final public function set(string $var, mixed $value): static
    {
        $this->container[$var] = $value;
        $this->touch();
        $this->addModifiedColumn($var);

        return $this;
    }

    /**
     * When the corresponding attribute is an array, call this method to set values.
     *
     * @throws ModelException
     */
    public function add(string $var, mixed $value): static
    {
        if ($this->has($var)) {
            if (is_array($this->container[$var])) {
                $this->container[$var][] = $value;
            } else {
                throw new ModelException(sprintf("Field '%s' exists and is not an array.", $var));
            }
        } else {
            $this->container[$var] = [$value];
        }
        $this->touch();
        $this->addModifiedColumn($var);

        return $this;
    }

    /**
     * @see FlexibleEntityInterface
     */
    final public function clear(string $attribute): static
    {
        if ($this->has($attribute)) {
            unset($this->container[$attribute]);
            $this->touch();
            $this->removeModifiedColumn($attribute);
        }

        return $this;
    }

    /**
     * Allows dynamic methods getXxx, setXxx, hasXxx, addXxx or clearXxx.
     *
     * @throws  ModelException if method does not exist.
     */
    public function __call(mixed $method, mixed $arguments): mixed
    {
        [$operation, $attribute] = $this->extractMethodName($method);

        return match ($operation) {
            'set' => $this->set($attribute, $arguments[0]),
            'get' => $this->get($attribute),
            'add' => $this->add($attribute, $arguments[0]),
            'has' => $this->has($attribute),
            'clear' => $this->clear($attribute),
            default => throw new ModelException(sprintf('No such method "%s:%s()"', $this::class, $method)),
        };
    }

    /** Make all keys lowercase and hydrate the object. */
    public function convert(array $values): FlexibleEntityInterface
    {
        $tmp = [];

        foreach ($values as $key => $value) {
            $tmp[strtolower((string)$key)] = $value;
        }

        return $this->hydrate($tmp);
    }

    /**
     * Returns the fields flatten as arrays.
     *
     * The complex stuff in here is when there is an array, since all elements
     * in arrays are the same type, we check only its first value to know if we need
     * to traverse it or not.
     *
     * @see FlexibleEntityInterface
     */
    public function extract(): array
    {
        $arrayRecurse = function ($val) use (&$arrayRecurse) {
            if (is_scalar($val)) {
                return $val;
            }

            if (is_array($val)) {
                if (is_array(current($val)) || (current($val) instanceof FlexibleEntityInterface)) {
                    return array_map($arrayRecurse, $val);
                } else {
                    return $val;
                }
            }

            if ($val instanceof FlexibleEntityInterface) {
                return $val->extract();
            }

            return $val;
        };


        return array_map($arrayRecurse, array_merge($this->container, $this->getCustomFields()));
    }

    /**
     * getCustomFields
     *
     * Return a list of custom methods with has() accessor.
     *
     * @access  private
     * @return  array
     */
    private function getCustomFields(): array
    {
        if (static::$hasMethods === null) {
            static::fillHasMethods($this);
        }

        $customFields = [];

        foreach (static::$hasMethods as $method) {
            if (call_user_func([$this, sprintf("has%s", $method)]) === true) {
                $customFields[Inflector::underscore(lcfirst($method))] = call_user_func(
                    [$this, sprintf("get%s", $method)]
                );
            }
        }

        return $customFields;
    }

    /** @see FlexibleEntityInterface */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_merge($this->container, $this->getCustomFields()));
    }

    /**
     * PHP magic to set attributes.
     *
     * @param string $var Attribute name.
     * @param mixed $value Attribute value.
     * @return void
     */
    public function __set(string $var, mixed $value): void
    {
        $methodName = "set" . Inflector::studlyCaps($var);
        $this->$methodName($value);
    }

    /**
     * PHP magic to get attributes.
     *
     * @param string $var Attribute name.
     * @return mixed Attribute value.
     */
    public function __get(string $var): mixed
    {
        $methodName = "get" . Inflector::studlyCaps($var);

        return $this->$methodName();
    }

    /**
     * Easy value check.
     *
     * @param string $var
     * @return  bool
     */
    public function __isset(string $var): bool
    {
        $methodName = "has" . Inflector::studlyCaps($var);

        return $this->$methodName();
    }

    /**
     * Clear an attribute.
     *
     * @param string $var
     * @return void
     */
    public function __unset(string $var): void
    {
        $methodName = "clear" . Inflector::studlyCaps($var);
        $this->$methodName();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        $methodName = "has" . Inflector::studlyCaps($offset);

        return $this->$methodName();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->__set($offset, $value);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetGet(mixed $offset): mixed
    {
        return $this->__get($offset);
    }

    /**
     * {@inheritdoc}
     */
    public function offsetUnset(mixed $offset): void
    {
        $this->clear($offset);
    }

    /**
     * When getIterator is called the first time, the list of "has" methods is set in a static attribute to boost
     * performances.
     */
    protected static function fillHasMethods(FlexibleEntity $entity): void
    {
        static::$hasMethods = [];

        foreach (get_class_methods($entity) as $method) {
            if (preg_match('/^has([A-Z].*)$/', (string)$method, $matches)) {
                static::$hasMethods[] = $matches[1];
            }
        }
    }
}

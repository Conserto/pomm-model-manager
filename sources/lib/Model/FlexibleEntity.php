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
 * FlexibleEntity
 *
 * Parent for entity classes.
 *
 * @abstract
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 */
abstract class FlexibleEntity extends FlexibleContainer implements \ArrayAccess
{
    public static bool $strict = true;
    protected static ?array $has_methods = null;

    /**
     * __construct
     *
     * Instantiate the entity and hydrate it with the given values.
     *
     * @access public
     * @param array|null $values Optional starting values.
     */
    public function __construct(array $values = null)
    {
        if ($values !== null) {
            $this->hydrate($values);
        }
    }

    /**
     * get
     *
     * Returns the $var value
     *
     * @final
     * @access public
     * @param  string|array $var Key(s) you want to retrieve value from.
     * @throws  ModelException if strict and the attribute does not exist.
     * @return mixed
     */
    final public function get(string|array $var): mixed
    {
        if (is_scalar($var)) {
            if ($this->has($var)) {
                return $this->container[$var];
            } elseif (static::$strict === true) {
                throw new ModelException(sprintf("No such key '%s'.", $var));
            }
        } elseif (is_array($var)) {
            return array_intersect_key($this->container, array_flip($var));
        }

        return null;
    }

    /**
     * has
     *
     * Returns true if the given key exists.
     *
     * @final
     * @access public
     * @param string $var
     * @return boolean
     */
    final public function has(string $var): bool
    {
        return isset($this->container[$var]) || array_key_exists($var, $this->container);
    }

    /**
     * set
     *
     * Set a value in the var holder.
     *
     * @final
     * @access public
     * @param String $var   Attribute name.
     * @param  Mixed          $value Attribute value.
     * @return FlexibleEntity $this
     */
    final public function set(string $var, mixed $value): FlexibleEntity
    {
        $this->container[$var] = $value;
        $this->touch();

        return $this;
    }

    /**
     * add
     *
     * When the corresponding attribute is an array, call this method
     * to set values.
     *
     * @access public
     * @param string $var
     * @param  mixed          $value
     * @return FlexibleEntity $this
     * @throws ModelException
     */
    public function add(string $var, mixed $value): FlexibleEntity
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

        return $this;
    }

    /**
     * clear
     *
     * Drop an attribute from the var holder.
     *
     * @final
     * @access public
     * @param String $offset Attribute name.
     * @return FlexibleEntity $this
     */
    final public function clear(string $offset): FlexibleEntity
    {
        if ($this->has($offset)) {
            unset($this->container[$offset]);
            $this->touch();
        }

        return $this;
    }

    /**
     * __call
     *
     * Allows dynamic methods getXxx, setXxx, hasXxx, addXxx or clearXxx.
     *
     * @access  public
     * @param   mixed $method
     * @param mixed $arguments
     * @return  mixed
     *@throws  ModelException if method does not exist.
     */
    public function __call(mixed $method, mixed $arguments)
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

    /**
     * convert
     *
     * Make all keys lowercase and hydrate the object.
     *
     * @access  public
     * @param array $values
     * @return  FlexibleEntityInterface
     */
    public function convert(array $values): FlexibleEntityInterface
    {
        $tmp = [];

        foreach ($values as $key => $value) {
            $tmp[strtolower((string) $key)] = $value;
        }

        return $this->hydrate($tmp);
    }

    /**
     * extract
     *
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
        $array_recurse = function ($val) use (&$array_recurse) {
            if (is_scalar($val)) {
                return $val;
            }

            if (is_array($val)) {
                if (is_array(current($val)) || (is_object(current($val)) && current($val) instanceof FlexibleEntityInterface)) {
                    return array_map($array_recurse, $val);
                } else {
                    return $val;
                }
            }

            if ($val instanceof FlexibleEntityInterface) {
                return $val->extract();
            }

            return $val;
        };


        return array_map($array_recurse, array_merge($this->container, $this->getCustomFields()));
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
        if (static::$has_methods === null) {
            static::fillHasMethods($this);
        }

        $custom_fields = [];

        foreach (static::$has_methods as $method) {
            if (call_user_func([$this, sprintf("has%s", $method)]) === true) {
                $custom_fields[Inflector::underscore(lcfirst($method))] = call_user_func([$this, sprintf("get%s", $method)]);
            }
        }

        return $custom_fields;
    }

    /**
     * getIterator
     *
     * @see FlexibleEntityInterface
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator(array_merge($this->container, $this->getCustomFields()));
    }

    /**
     * __set
     *
     * PHP magic to set attributes.
     *
     * @access  public
     * @param String $var   Attribute name.
     * @param   Mixed          $value Attribute value.
     * @return  FlexibleEntity $this
     */
    public function __set(string $var, mixed $value)
    {
        $method_name = "set".Inflector::studlyCaps($var);
        $this->$method_name($value);

        return $this;
    }

    /**
     * __get
     *
     * PHP magic to get attributes.
     *
     * @access  public
     * @param String $var Attribute name.
     * @return  Mixed  Attribute value.
     */
    public function __get(string $var)
    {
        $method_name = "get".Inflector::studlyCaps($var);

        return $this->$method_name();
    }

    /**
     * __isset
     *
     * Easy value check.
     *
     * @access  public
     * @param string $var
     * @return  bool
     */
    public function __isset(string $var)
    {
        $method_name = "has".Inflector::studlyCaps($var);

        return $this->$method_name();
    }

    /**
     * __unset
     *
     * Clear an attribute.
     *
     * @access  public
     * @param string $var
     * @return void $this
     */
    public function __unset(string $var): void
    {
        $method_name = "clear".Inflector::studlyCaps($var);
        $this->$method_name();
    }

    /**
     * {@inheritdoc}
     */
    public function offsetExists(mixed $offset): bool
    {
        $method_name = "has".Inflector::studlyCaps($offset);

        return $this->$method_name();
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
     * fillHasMethods
     *
     * When getIterator is called the first time, the list of "has" methods is
     * set in a static attribute to boost performances.
     *
     * @access  protected
     * @param   FlexibleEntity   $entity
     * @return  void
     */
    protected static function fillHasMethods(FlexibleEntity $entity): void
    {
        static::$has_methods = [];

        foreach (get_class_methods($entity) as $method) {
            if (preg_match('/^has([A-Z].*)$/', (string) $method, $matches)) {
                static::$has_methods[] = $matches[1];
            }
        }
    }
}

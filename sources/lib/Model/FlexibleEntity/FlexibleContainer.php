<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model\FlexibleEntity;

use PommProject\Foundation\Inflector;
use PommProject\ModelManager\Exception\ModelException;

/**
 * Trait for being a flexible data container.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
abstract class FlexibleContainer implements FlexibleEntityInterface, \IteratorAggregate
{
    use StatefulEntityTrait;
    use ModifiedColumnEntityTrait;

    /** @var array<string, mixed> */
    protected array $container = [];

    /**
     * @see FlexibleEntityInterface
     *
     * @param array<string, mixed> $fields
     */
    public function hydrate(array $fields): self
    {
        $this->container = array_merge($this->container, $fields);

        return $this;
    }

    /**
     * Return the fields array. If a given field does not exist, an exception is thrown.
     *
     * @throws  \InvalidArgumentException
     * @see     FlexibleEntityInterface
     *
     * @param array<string> $fields
     * @return array<string, mixed>
     */
    public function fields(array $fields = null): array
    {
        if ($fields === null) {
            return $this->container;
        }

        $output = [];

        foreach ($fields as $name) {
            if (isset($this->container[$name]) || array_key_exists($name, $this->container)) {
                $output[$name] = $this->container[$name];
            } else {
                throw new \InvalidArgumentException(
                    sprintf(
                        "No such field '%s'. Existing fields are {%s}",
                        $name,
                        join(', ', array_keys($this->container))
                    )
                );
            }
        }

        return $output;
    }

    /**
     * @see FlexibleEntityInterface
     *
     * @return array<string, mixed>
     */
    public function extract(): array
    {
        return $this->fields();
    }

    /**
     * @see FlexibleEntityInterface
     *
     * @throws ModelException
     */
    public function clear(string $attribute): self
    {
        unset($this->checkAttribute($attribute)->container[$attribute]);
        return $this;
    }

    /**
     * @see FlexibleEntityInterface
     *
     * @return \Traversable<string, mixed>
     */
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->extract());
    }

    /**
     * Allows dynamic methods getXxx, setXxx, hasXxx or clearXxx.
     *
     * @throws ModelException if method does not exist.
     */
    public function __call(mixed $method, mixed $arguments): mixed
    {
        [$operation, $attribute] = $this->extractMethodName($method);
        $returned = $this;

        switch ($operation) {
        case 'set':
            $this->container[$attribute] = $arguments[0];
            break;
        case 'get':
            $returned = $this->checkAttribute($attribute)->container[$attribute];
            break;
        case 'has':
            $returned = isset($this->container[$attribute]) || array_key_exists($attribute, $this->container);
            break;
        case 'clear':
            $this->clear($attribute);
            break;
        default:
            throw new ModelException(sprintf('No such method "%s:%s()"', $this::class, $method));
        }

        return $returned;
    }

    /**
     * Check if the attribute exist. Throw an exception if not.
     *
     * @throws ModelException
     */
    protected function checkAttribute(string $attribute): self
    {
        if (!(isset($this->container[$attribute]) || array_key_exists($attribute, $this->container))) {
            throw new ModelException(
                sprintf(
                    "No such attribute '%s'. Available attributes are {%s}",
                    $attribute,
                    join(", ", array_keys($this->fields()))
                )
            );
        }

        return $this;
    }

    /**
     * Get container field name from method name.
     * It returns an array with the operation (get, set, etc.) as first member
     * and the name of the attribute as second member.
     *
     * @throws ModelException
     *
     * @return array{0: string, 1: string}
     */
    protected function extractMethodName(string $argument): array
    {
        $split = preg_split('/(?=[A-Z])/', $argument, 2);

        if ((is_countable($split) ? count($split) : 0) !== 2) {
            throw new ModelException(sprintf('No such argument "%s:%s()"', $this::class, $argument));
        }

        return [$split[0], Inflector::underscore($split[1])];
    }
}

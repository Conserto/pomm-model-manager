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

use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Converter\ConverterInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Tell the FlexibleEntityConverter how to hydrate fields.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class HydrationPlan
{
    protected array $converters = [];
    protected array $fieldTypes = [];


    /**
     * @throws FoundationException
     * @throws ModelException
     */
    public function __construct(protected Projection $projection, protected Session $session)
    {
        $this->loadConverters();
    }

    /**
     * Cache converters needed for this result set.
     *
     * @throws FoundationException|ModelException
     */
    protected function loadConverters(): self
    {
        foreach ($this->projection as $name => $type) {
            $identifier = $this->projection->isArray($name) ? 'array' : $type;

            /** @var ConverterClient $converterClient */
            $converterClient = $this->session
                ->getClientUsingPooler('converter', $identifier);

            $this->converters[$name] = $converterClient->getConverter();
            $this->fieldTypes[$name] = $type;
        }

        return $this;
    }


    /**
     * Return the type of the given field. Proxy to Projection::getFieldType().
     *
     * @throws ModelException
     */
    public function getFieldType(string $name): string
    {
        return $this->projection->getFieldType($name);
    }

    /**
     * Tell if the given field is an array or not.
     *
     * @throws ModelException
     */
    public function isArray(string $name): bool
    {
        return $this->projection->isArray($name);
    }


    /**
     * Take values fetched from the database, launch conversion system and hydrate the FlexibleEntityInterface through
     * the mapper.
     */
    public function hydrate(array $values): FlexibleEntityInterface
    {
        $values = $this->convert('fromPg', $values);

        return $this->createEntity($values);
    }

    /** Return values converted to Pg. */
    public function dry(array $values): array
    {
        return $this->convert('toPg', $values);
    }

    /**
     * Return values converted to Pg standard output.
     *
     * @param  array $values
     * @return array converted values
     */
    public function freeze(array $values): array
    {
        return $this->convert('toPgStandardFormat', $values);
    }

    /** Convert values from / to postgres. */
    protected function convert(string $fromTo, array $values): array
    {
        $outValues = [];

        foreach ($values as $name => $value) {
            if (isset($this->converters[$name])) {
                $outValues[$name] = $this
                    ->converters[$name]
                    ->$fromTo($value, $this->fieldTypes[$name], $this->session);
            } else {
                $outValues[$name] = $value;
            }
        }

        return $outValues;
    }

    /** Instantiate FlexibleEntityInterface from converted values. */
    protected function createEntity(array $values): FlexibleEntityInterface
    {
        $class = $this->projection->getFlexibleEntityClass();

        return (new $class())->hydrate($values);
    }

    /** Return the converter client associated with a field. */
    public function getConverterForField(string $fieldName): ConverterInterface
    {
        if (!isset($this->converters[$fieldName])) {
            throw new \RuntimeException(
                sprintf(
                    "Error, '%s' field has no converters registered. Fields are {%s}.",
                    $fieldName,
                    join(', ', array_keys($this->converters))
                )
            );
        }

        return $this->converters[$fieldName];
    }

    /** Permet de supprimer un converter */
    public function removeConverter(string $converterClass): self
    {
        foreach ($this->converters as $field => $converter) {
            if ($converter instanceof $converterClass) {
                unset($this->converters[$field]);
            }
        }

        return $this;
    }
}

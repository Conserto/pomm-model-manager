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
 * HydrationPlan
 *
 * Tell the FlexibleEntityConverter how to hydrate fields.
 *
 * @package     ModelManager
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class HydrationPlan
{
    protected array $converters = [];
    protected array $field_types = [];


    /**
     * Construct
     *
     * @access  public
     * @param Projection $projection
     * @param Session $session
     * @throws FoundationException
     * @throws ModelException
     */
    public function __construct(protected Projection $projection, protected Session $session)
    {
        $this->loadConverters();
    }

    /**
     * loadConverters
     *
     * Cache converters needed for this result set.
     *
     * @access protected
     * @return HydrationPlan    $this
     * @throws FoundationException|ModelException
     */
    protected function loadConverters(): HydrationPlan
    {
        foreach ($this->projection as $name => $type) {
            $identifier = $this->projection->isArray($name) ? 'array' : $type;

            /** @var ConverterClient $converterClient */
            $converterClient = $this
                ->session
                ->getClientUsingPooler('converter', $identifier);

            $this->converters[$name] = $converterClient->getConverter();
            $this->field_types[$name] = $type;
        }

        return $this;
    }


    /**
     * getFieldType
     *
     * Return the type of the given field. Proxy to Projection::getFieldType().
     *
     * @access public
     * @param string $name
     * @return string
     * @throws ModelException
     */
    public function getFieldType(string $name): string
    {
        return $this->projection->getFieldType($name);
    }

    /**
     * isArray
     *
     * Tell if the given field is an array or not.
     *
     * @access public
     * @param string $name
     * @return bool
     * @throws ModelException
     */
    public function isArray(string $name): bool
    {
        return $this->projection->isArray($name);
    }


    /**
     * hydrate
     *
     * Take values fetched from the database, launch conversion system and
     * hydrate the FlexibleEntityInterface through the mapper.
     *
     * @access public
     * @param  array $values
     * @return FlexibleEntityInterface
     */
    public function hydrate(array $values): FlexibleEntityInterface
    {
        $values = $this->convert('fromPg', $values);

        return $this->createEntity($values);
    }

    /**
     * dry
     *
     * Return values converted to Pg.
     *
     * @access public
     * @param  array    $values
     * @return array
     */
    public function dry(array $values): array
    {
        return $this->convert('toPg', $values);
    }

    /**
     * freeze
     *
     * Return values converted to Pg standard output.
     *
     * @access public
     * @param  array $values
     * @return array converted values
     */
    public function freeze(array $values): array
    {
        return $this->convert('toPgStandardFormat', $values);
    }

    /**
     * convert
     *
     * Convert values from / to postgres.
     *
     * @access protected
     * @param string $from_to
     * @param  array    $values
     * @return array
     */
    protected function convert(string $from_to, array $values): array
    {
        $out_values = [];

        foreach ($values as $name => $value) {
            if (isset($this->converters[$name])) {
                $out_values[$name] = $this
                    ->converters[$name]
                    ->$from_to($value, $this->field_types[$name], $this->session)
                    ;
            } else {
                $out_values[$name] = $value;
            }
        }

        return $out_values;
    }

    /**
     * createEntity
     *
     * Instantiate FlexibleEntityInterface from converted values.
     *
     * @access protected
     * @param  array $values
     * @return FlexibleEntityInterface
     */
    protected function createEntity(array $values): FlexibleEntityInterface
    {
        $class = $this->projection->getFlexibleEntityClass();

        return (new $class())
            ->hydrate($values)
            ;
    }

    /**
     * getConverterForField
     *
     * Return the converter client associated with a field.
     *
     * @access public
     * @param string $field_name
     * @return ConverterInterface
     */
    public function getConverterForField(string $field_name): ConverterInterface
    {
        if (!isset($this->converters[$field_name])) {
            throw new \RuntimeException(
                sprintf(
                    "Error, '%s' field has no converters registered. Fields are {%s}.",
                    $field_name,
                    join(', ', array_keys($this->converters))
                )
            );
        }

        return $this->converters[$field_name];
    }
}

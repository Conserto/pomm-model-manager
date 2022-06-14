<?php
/*
 * This file is part of the PommProject's ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model;

use PommProject\Foundation\Converter\ConverterClient;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\ResultIterator;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Converter\PgEntity;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * CollectionIterator
 *
 * Iterator for query results.
 *
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class CollectionIterator extends ResultIterator
{
    /**
     * @var array
     */
    protected array $filters = [];

    /**
     * @var HydrationPlan
     */
    protected HydrationPlan $hydration_plan;

    private readonly PgEntity $entity_converter;

    /**
     * __construct
     *
     * Constructor
     *
     * @access  public
     * @param ResultHandler $result
     * @param Session $session
     * @param Projection $projection
     * @throws FoundationException|ModelException
     */
    public function __construct(ResultHandler $result, protected Session $session, protected Projection $projection)
    {
        parent::__construct($result);
        $this->hydration_plan   = new HydrationPlan($projection, $session);

        /** @var ConverterClient $converterClient */
        $converterClient = $this
            ->session
            ->getClientUsingPooler('converter', $this->projection->getFlexibleEntityClass());

        /** @var PgEntity $converter */
        $converter = $converterClient->getConverter();

        $this->entity_converter = $converter;
    }

    /**
     * get
     *
     * @param $index
     * @return  FlexibleEntityInterface
     * @see     ResultIterator
     */
    public function get($index): FlexibleEntityInterface
    {
        return $this->parseRow(parent::get($index));
    }

    /**
     * parseRow
     *
     * Convert values from Pg.
     *
     * @access  protected
     * @param array $values
     * @return  FlexibleEntityInterface
     * @throws ModelException
     * @see     ResultIterator
     */
    public function parseRow(array $values): FlexibleEntityInterface
    {
        $values = $this->launchFilters($values);
        $entity = $this->hydration_plan->hydrate($values);

        return $this->entity_converter->cacheEntity($entity);
    }

    /**
     * launchFilters
     *
     * Launch filters on the given values.
     *
     * @access  protected
     * @param   array $values
     * @throws  ModelException   if return is not an array.
     * @return  array
     */
    protected function launchFilters(array $values): array
    {
        foreach ($this->filters as $filter) {
            $values = call_user_func($filter, $values);

            if (!is_array($values)) {
                throw new ModelException("Filter error. Filters MUST return an array of values.");
            }
        }

        return $values;
    }

    /**
     * registerFilter
     *
     * Register a new callable filter. All filters MUST return an associative
     * array with field name as key.
     *
     * @access public
     * @param callable $callable the filter.
     * @return CollectionIterator $this
     */
    public function registerFilter(callable $callable): CollectionIterator
    {
        $this->filters[] = $callable;

        return $this;
    }

    /**
     * clearFilters
     *
     * Empty the filter stack.
     */
    public function clearFilters(): CollectionIterator
    {
        $this->filters = [];

        return $this;
    }

    /**
     * extract
     *
     * Return an array of entities extracted as arrays.
     *
     * @access public
     * @return array
     */
    public function extract(): array
    {
        $results = [];

        foreach ($this as $result) {
            $results[] = $result->extract();
        }

        return $results;
    }

    /**
     * slice
     *
     * see @ResultIterator
     *
     * @access public
     * @param string $field
     * @return array
     * @throws ModelException
     */
    public function slice(string $field): array
    {
        return $this->convertSlice(parent::slice($field), $field);
    }


    /**
     * convertSlice
     *
     * Convert a slice.
     *
     * @access protected
     * @param array $values
     * @param string $name
     * @return array
     * @throws ModelException
     */
    protected function convertSlice(array $values, string $name): array
    {
        $type = $this->projection->getFieldType($name);
        $converter = $this->hydration_plan->getConverterForField($name);

        return array_map(
            fn($val) => $converter->fromPg($val, $type, $this->session),
            $values
        );
    }
}

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
 * Iterator for query results.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT <hubert.greg@gmail.com>
 * @license   MIT/X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @template-covariant T of FlexibleEntityInterface
 * @extends ResultIterator<T>
 */
class CollectionIterator extends ResultIterator
{
    protected array $filters = [];

    /**
     * @var HydrationPlan
     */
    protected HydrationPlan $hydrationPlan;

    private readonly PgEntity $entityConverter;

    /**
     * @throws FoundationException|ModelException
     */
    public function __construct(ResultHandler $result, protected Session $session, protected Projection $projection)
    {
        parent::__construct($result);
        $this->hydrationPlan   = new HydrationPlan($projection, $session);

        /** @var ConverterClient $converterClient */
        $converterClient = $this
            ->session
            ->getClientUsingPooler('converter', $this->projection->getFlexibleEntityClass());

        /** @var PgEntity $converter */
        $converter = $converterClient->getConverter();

        $this->entityConverter = $converter;
    }

    /**
     * @throws ModelException
     * @see     ResultIterator
     *
     * @return T
     */
    public function get(int $index): FlexibleEntityInterface
    {
        return $this->parseRow(parent::get($index));
    }

    /**
     * Convert values from Pg.
     *
     * @throws ModelException
     * @see     ResultIterator
     *
     * @return T
     */
    public function parseRow(array $values): FlexibleEntityInterface
    {
        $values = $this->launchFilters($values);
        $entity = $this->hydrationPlan->hydrate($values);

        return $this->entityConverter->cacheEntity($entity);
    }

    /**
     * Launch filters on the given values.
     *
     * @throws  ModelException   if return is not an array.
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

    /** Register a new callable filter. All filters MUST return an associative array with field name as key. */
    public function registerFilter(callable $callable): self
    {
        $this->filters[] = $callable;

        return $this;
    }

    /** Empty the filter stack. */
    public function clearFilters(): self
    {
        $this->filters = [];

        return $this;
    }

    /** Return an array of entities extracted as arrays. */
    public function extract(): array
    {
        $results = [];

        foreach ($this as $result) {
            $results[] = $result->extract();
        }

        return $results;
    }

    /**
     * see @ResultIterator
     *
     * @throws ModelException
     */
    public function slice(string $field): array
    {
        return $this->convertSlice(parent::slice($field), $field);
    }


    /**
     * Convert a slice.
     *
     * @throws ModelException
     */
    protected function convertSlice(array $values, string $name): array
    {
        $type = $this->projection->getFieldType($name);
        $converter = $this->hydrationPlan->getConverterForField($name);

        return array_map(
            fn($val) => $converter->fromPg($val, $type, $this->session),
            $values
        );
    }
}

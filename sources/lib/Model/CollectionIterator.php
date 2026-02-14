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
 * @template T of FlexibleEntityInterface
 * @extends ResultIterator<T>
 * @phpstan-type Filter = callable(array<string, mixed>): array<string, mixed>
 */
class CollectionIterator extends ResultIterator
{
    /** @var array<int, Filter> */
    protected array $filters = [];

    /** @var HydrationPlan<T> $hydrationPlan */
    protected HydrationPlan $hydrationPlan;

    /** @var PgEntity<T> */
    private readonly PgEntity $entityConverter;

    /**
     * @param Projection<T> $projection
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

        /** @var PgEntity<T> $converter */
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
        /** @var array<string, mixed> $row */
        $row = parent::get($index);
        return $this->parseRow($row);
    }

    /**
     * Convert values from Pg.
     * @param  array<string, mixed> $values
     * @return T
     * @throws ModelException
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
    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     * @throws ModelException
     */
    protected function launchFilters(array $values): array
    {
        foreach ($this->filters as $filter) {
            $values = call_user_func($filter, $values);
        }

        return $values;
    }

    /**
     * Register a new callable filter. All filters MUST return an associative array with field name as key.
     * @param Filter $callable
     * @return $this
     */
    public function registerFilter(callable $callable): static
    {
        $this->filters[] = $callable;

        return $this;
    }

    /** Empty the filter stack. */
    public function clearFilters(): static
    {
        $this->filters = [];

        return $this;
    }

    /**
     * Return an array of entities extracted as arrays.
     * @return array<int, array<string, mixed>>
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
     * see @ResultIterator
     * @param string $field
     * @return array<int, mixed>
     * @throws ModelException
     */
    public function slice(string $field): array
    {
        return $this->convertSlice(parent::slice($field), $field);
    }


    /**
     * Convert a slice.
     * @param array<int, mixed> $values Values coming from ResultIterator::slice()
     * @param string $name
     * @return array<int, mixed>
     * @throws ModelException
     */
    protected function convertSlice(array $values, string $name): array
    {
        $type = $this->projection->getFieldType($name);
        if ($type === null) {
            throw new ModelException(sprintf("No type defined for field '%s'.", $name));
        }

        $converter = $this->hydrationPlan->getConverterForField($name);

        return array_map(
            function (mixed $val) use ($converter, $type): mixed {
                if (!is_string($val) && $val !== null) {
                    throw new ModelException(sprintf(
                        "Unexpected slice value type '%s', expected string|null.",
                        gettype($val)
                    ));
                }

                return $converter->fromPg($val, $type, $this->session);
            },
            $values
        );
    }
}

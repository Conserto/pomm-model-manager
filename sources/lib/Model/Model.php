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

use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Converter\ConverterPooler;
use PommProject\Foundation\Exception\ConnectionException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Exception\SqlException;
use PommProject\Foundation\PreparedQuery\PreparedQuery;
use PommProject\Foundation\Session\ResultHandler;
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Converter\PgEntity;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Base class for custom Model classes.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         ClientInterface
 *
 * @template T of FlexibleEntityInterface
 */
abstract class Model implements ClientInterface
{
    protected ?Session $session = null;

    /** @var class-string<T>|null  */
    protected ?string $flexibleEntityClass = null;

    protected ?RowStructure $structure = null;

    /**
     * Return the current session. If session is not set, a ModelException is thrown.
     *
     * @throws ModelException
     */
    public function getSession(): Session
    {
        if ($this->session === null) {
            throw new ModelException(sprintf("Model class '%s' is not registered against the session.", $this::class));
        }

        return $this->session;
    }

    /** @see ClientInterface */
    public function getClientType(): string
    {
        return 'model';
    }

    /** @see ClientInterface */
    public function getClientIdentifier(): string
    {
        return trim($this::class, "\\");
    }

    /**
     * @throws ModelException
     * @throws FoundationException|\ReflectionException
     * @see ClientInterface
     */
    public function initialize(Session $session): void
    {
        $this->session = $session;

        // Check structure is set
        $this->getStructure();

        // Check flexible entity class is set
        $this->getFlexibleEntityClass();

        /** @var ConverterPooler $converterPooler */
        $converterPooler = $session->getPoolerForType('converter');

        $converterPooler
            ->getConverterHolder()
            ->registerConverter(
                $this->flexibleEntityClass,
                new PgEntity($this->flexibleEntityClass, $this->getStructure()),
                [
                    $this->getStructure()->getRelation(),
                    $this->flexibleEntityClass,
                ]
            );
    }

    /** @see ClientInterface */
    public function shutdown(): void
    {
    }

    /**
     * Create a new entity.
     *
     * @throws ModelException
     * @throws \ReflectionException
     */
    public function createEntity(array $values = []): FlexibleEntityInterface
    {
        $className = $this->getFlexibleEntityClass();

        return (new $className)->hydrate($values);
    }

    /**
     * Execute the given query and return a Collection iterator on results. If no projections are passed, it will use
     * the default projection using createProjection() method.
     *
     * @throws FoundationException|ModelException|SqlException
     *
     * @param Projection|null $projection
     * @param string $sql
     * @param array $values
     *
     * @return CollectionIterator<T>
     */
    protected function query(string $sql, array $values = [], Projection $projection = null): CollectionIterator
    {
        if ($projection === null) {
            $projection = $this->createProjection();
        }

        /** @var PreparedQuery $prepareQuery */
        $prepareQuery = $this
            ->getSession()
            ->getClientUsingPooler('prepared_query', $sql);

        $result = $prepareQuery->execute($values);

        return new CollectionIterator(
            $result,
            $this->getSession(),
            $projection
        );
    }

    /**
     * This method creates a projection based on the structure definition of the underlying relation. It may be used to
     * shunt parent createProjection call in inherited classes.
     * This method can be used where a projection that sticks to table definition is needed like recursive CTEs.
     * For normal projections, use createProjection instead.
     */
    final public function createDefaultProjection(): Projection
    {
        return new Projection($this->flexibleEntityClass, $this->structure->getDefinition());
    }

    /**
     * This is a helper to create a new projection according to the current structure.Overriding this method will change
     * projection for all models.
     */
    public function createProjection(): Projection
    {
        return $this->createDefaultProjection();
    }

    /**
     * Check if the given entity is an instance of this model's flexible class. If not an exception is thrown.
     *
     * @throws ModelException
     * @throws \ReflectionException
     */
    protected function checkFlexibleEntity(FlexibleEntityInterface $entity): Model
    {
        $flexibleEntityClass = $this->getFlexibleEntityClass();

        if (!($entity instanceof $flexibleEntityClass)) {
            throw new \InvalidArgumentException(
                sprintf("Entity class '%s' is not a '%s'.", $entity::class, $this->flexibleEntityClass)
            );
        }

        return $this;
    }

    /**
     * Return the structure.
     *
     * @throws ModelException
     */
    public function getStructure(): RowStructure
    {
        if ($this->structure === null) {
            throw new ModelException(sprintf("Structure not set while initializing Model class '%s'.", $this::class));
        }

        return $this->structure;
    }

    /**
     * Proxy to Session::getModel();
     *
     * @template TModel of Model
     * @param class-string<TModel> $identifier
     * @return TModel
     * @throws FoundationException
     * @throws ModelException
     */
    protected function getModel(string $identifier): Model
    {
        /** @var Model $modelManager */
        $modelManager = $this
            ->getSession()
            ->getClientUsingPooler('model', $identifier);

        return $modelManager;
    }

    /**
     * Return the according flexible entity class associate with this Model instance.
     *
     * @throws \ReflectionException|ModelException
     */
    public function getFlexibleEntityClass(): string
    {
        if ($this->flexibleEntityClass == null) {
            throw new ModelException(
                sprintf("Flexible entity not set while initializing Model class '%s'.", $this::class)
            );
        } elseif (!(new \ReflectionClass($this->flexibleEntityClass))
            ->implementsInterface(FlexibleEntityInterface::class)
        ) {
            throw new ModelException("Flexible entity must implement FlexibleEntityInterface.");
        }

        return $this->flexibleEntityClass;
    }

    /**
     * Handy method to escape strings.
     *
     * @throws ConnectionException
     * @throws ModelException
     * @throws FoundationException
     */
    protected function escapeLiteral(string $string): string
    {
        return $this
            ->getSession()
            ->getConnection()
            ->escapeLiteral($string);
    }

    /**
     * Handy method to escape strings.
     *
     * @throws ModelException
     * @throws ConnectionException
     * @throws FoundationException
     */
    protected function escapeIdentifier(string $string): string
    {
        return $this
            ->getSession()
            ->getConnection()
            ->escapeIdentifier($string);
    }

    /**
     * Handy method for DDL statements.
     *
     * @throws ConnectionException|ModelException|SqlException|FoundationException
     */
    protected function executeAnonymousQuery(string $sql): ResultHandler|array
    {
        return $this->getSession()
            ->getConnection()
            ->executeAnonymousQuery($sql);
    }
}

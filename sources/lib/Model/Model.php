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
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Converter\PgEntity;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Model
 *
 * Base class for custom Model classes.
 *
 * @abstract
 * @package     Pomm
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         ClientInterface
 */
abstract class Model implements ClientInterface
{
    protected ?Session $session = null;
    protected ?string $flexible_entity_class = null;


    /**
     * @var RowStructure|null
     */
    protected ?RowStructure $structure = null;

    /**
     * getSession
     *
     * Return the current session. If session is not set, a ModelException is
     * thrown.
     *
     * @access public
     * @return Session
     * @throws ModelException
     */
    public function getSession(): Session
    {
        if ($this->session === null) {
            throw new ModelException(sprintf("Model class '%s' is not registered against the session.", $this::class));
        }

        return $this->session;
    }

    /**
     * getClientType
     *
     * @see ClientInterface
     */
    public function getClientType(): string
    {
        return 'model';
    }

    /**
     * getClientIdentifier
     *
     * @see ClientInterface
     */
    public function getClientIdentifier(): string
    {
        return trim($this::class, "\\");
    }

    /**
     * initialize
     *
     * @param Session $session
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
                $this->flexible_entity_class,
                new PgEntity(
                    $this->flexible_entity_class,
                    $this->getStructure()
                ),
                [
                    $this->getStructure()->getRelation(),
                    $this->flexible_entity_class,
                ]
            );
    }

    /**
     * shutdown
     *
     * @see ClientInterface
     */
    public function shutdown(): void
    {
    }

    /**
     * createEntity
     *
     * Create a new entity.
     *
     * @access public
     * @param array $values
     * @return FlexibleEntityInterface
     * @throws ModelException
     * @throws \ReflectionException
     */
    public function createEntity(array $values = []): FlexibleEntityInterface
    {
        $class_name = $this->getFlexibleEntityClass();

        return (new $class_name)
            ->hydrate($values);
    }

    /**
     * query
     *
     * Execute the given query and return a Collection iterator on results. If
     * no projections are passed, it will use the default projection using
     * createProjection() method.
     *
     * @access protected
     * @param string $sql
     * @param array $values
     * @param Projection|null $projection
     * @return CollectionIterator
     * @throws FoundationException
     * @throws ModelException
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
     * createDefaultProjection
     *
     * This method creates a projection based on the structure definition of
     * the underlying relation. It may be used to shunt parent createProjection
     * call in inherited classes.
     * This method can be used where a projection that sticks to table
     * definition is needed like recursive CTEs. For normal projections, use
     * createProjection instead.
     *
     * @access public
     * @return Projection
     */
    final public function createDefaultProjection(): Projection
    {
        return new Projection($this->flexible_entity_class, $this->structure->getDefinition());
    }

    /**
     * createProjection
     *
     * This is a helper to create a new projection according to the current
     * structure.Overriding this method will change projection for all models.
     *
     * @access  public
     * @return  Projection
     */
    public function createProjection(): Projection
    {
        return $this->createDefaultProjection();
    }

    /**
     * checkFlexibleEntity
     *
     * Check if the given entity is an instance of this model's flexible class.
     * If not an exception is thrown.
     *
     * @access protected
     * @param FlexibleEntityInterface $entity
     * @return Model          $this
     * @throws ModelException
     * @throws \ReflectionException
     */
    protected function checkFlexibleEntity(FlexibleEntityInterface $entity): Model
    {
        $flexibleEntityClass = $this->getFlexibleEntityClass();

        if (!($entity instanceof $flexibleEntityClass)) {
            throw new \InvalidArgumentException(sprintf(
                "Entity class '%s' is not a '%s'.",
                $entity::class,
                $this->flexible_entity_class
            ));
        }

        return $this;
    }

    /**
     * getStructure
     *
     * Return the structure.
     *
     * @access public
     * @return RowStructure
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
     * getFlexibleEntityClass
     *
     * Return the according flexible entity class associate with this Model
     * instance.
     *
     * @access public
     * @return string
     * @throws \ReflectionException|ModelException
     */
    public function getFlexibleEntityClass(): string
    {
        if ($this->flexible_entity_class == null) {
            throw new ModelException(sprintf("Flexible entity not set while initializing Model class '%s'.", $this::class));
        } elseif (!(new \ReflectionClass($this->flexible_entity_class))
            ->implementsInterface(FlexibleEntityInterface::class)
        ) {
            throw new ModelException("Flexible entity must implement FlexibleEntityInterface.");
        }

        return $this->flexible_entity_class;
    }

    /**
     * escapeLiteral
     *
     * Handy method to escape strings.
     *
     * @access protected
     * @param string $string
     * @return string
     * @throws ConnectionException
     * @throws ModelException
     */
    protected function escapeLiteral(string $string): string
    {
        return $this
            ->getSession()
            ->getConnection()
            ->escapeLiteral($string);
    }

    /**
     * escapeLiteral
     *
     * Handy method to escape strings.
     *
     * @access protected
     * @param string $string
     * @return string
     * @throws ModelException
     * @throws ConnectionException
     */
    protected function escapeIdentifier(string $string): string
    {
        return $this
            ->getSession()
            ->getConnection()
            ->escapeIdentifier($string);
    }

    /**
     * executeAnonymousQuery
     *
     * Handy method for DDL statements.
     *
     * @access protected
     * @param string $sql
     * @return Model  $this
     * @throws ConnectionException|ModelException|SqlException|FoundationException
     */
    protected function executeAnonymousQuery(string $sql): Model
    {
        $this
            ->getSession()
            ->getConnection()
            ->executeAnonymousQuery($sql);

        return $this;
    }
}

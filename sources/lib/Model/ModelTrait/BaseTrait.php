<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model\ModelTrait;

use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Model\CollectionIterator;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;
use PommProject\ModelManager\Model\Model;
use PommProject\ModelManager\Model\Projection;
use PommProject\ModelManager\Model\RowStructure;

/**
 * BaseModelTrait
 *
 * Abstract methods for Model traits.
 *
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
trait BaseTrait
{
    /**
     * @see Model
     * @return Projection
     */
    abstract public function createProjection(): Projection;

    /**
     * @param string $sql
     * @param array $values
     * @param Projection|null $projection
     * @return CollectionIterator
     * @see Model
     */
    abstract protected function query(string $sql, array $values = [], Projection $projection = null): CollectionIterator;

    /**
     * @see Model
     * @return Session
     */
    abstract protected function getSession(): Session;

    /**
     * @return RowStructure|null
     * @see Model
     */
    abstract public function getStructure(): ?RowStructure;

    /**
     * @see Model
     */
    abstract public function getFlexibleEntityClass(): string;

    /**
     * @see Model
     */
    abstract public function escapeLiteral(string $string): string;

    /**
     * @see Model
     */
    abstract public function escapeIdentifier(string $string): string;

    /**
     * @see Model
     */
    abstract public function executeAnonymousQuery(string $sql): Model;

    /**
     * @see Model
     */
    abstract public function createEntity(array $values = []): FlexibleEntityInterface;
}

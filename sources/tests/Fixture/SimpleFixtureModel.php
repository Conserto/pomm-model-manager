<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Test\Fixture;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Where;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\CollectionIterator;
use PommProject\ModelManager\Model\Model;

class SimpleFixtureModel extends Model
{
    public function __construct()
    {
        $this->structure = new SimpleFixtureStructure();
        $this->flexibleEntityClass = SimpleFixture::class;
    }

    /**
     * @throws FoundationException
     * @throws ModelException
     */
    public function doSimpleQuery(?Where $where = null): CollectionIterator
    {
        if ($where === null) {
            $where = new Where();
        }

        $sql = strtr(
            "select :fields from :relation where :condition",
            [
                ':fields'    => $this->createProjection()->formatFieldsWithFieldAlias(),
                ':relation'  => $this->getStructure()->getRelation(),
                ':condition' => (string) $where,
            ]
        );

        return $this->query($sql, $where->getValues());
    }

    /**
     * @throws FoundationException
     * @throws ModelException
     */
    public function testGetModel(): bool
    {
        return $this === $this->getModel(self::class);
    }
}

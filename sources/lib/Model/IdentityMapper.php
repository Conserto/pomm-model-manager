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

use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Cache for FlexibleEntityInterface instances to ensure there are no different
 * instances for the same data.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class IdentityMapper
{
    /** @var FlexibleEntityInterface[] */
    protected array $instances = [];

    /**
     * Compute a unique signature upon entity's values in its primary key. If an empty primary key is provided, null is
     * returned.
     */
    public static function getSignature(FlexibleEntityInterface $entity, array $primaryKey): ?string
    {
        if (empty($primaryKey)) {
            return null;
        }

        return sha1(sprintf("%s|%s", serialize($entity->fields($primaryKey)), $entity::class));
    }

    /** Pool FlexibleEntityInterface instances and update them if necessary. */
    public function fetch(
        FlexibleEntityInterface $entity,
        array $primaryKey,
        RowStructure $rowStructure
    ): FlexibleEntityInterface {
        $signature = self::getSignature($entity, $primaryKey);

        if ($signature === null) {
            return $entity;
        }

        // "nettoyer" l'entité pour la mise en cache
        // suppression des données qui ne sont propres à l'entité
        $entityFields = $entity->fields();
        $structureFields = $rowStructure->getFieldNames();
        foreach ($entityFields as $key => $value) {
            if (!in_array($key, $structureFields)) {
                $entity->clear($key);
            }
        }

        if (!array_key_exists($signature, $this->instances)) {
            $this->instances[$signature] = $entity->hydrate(
                array_intersect_key($entityFields, array_flip($structureFields))
            );
            $entity->status(FlexibleEntityInterface::STATUS_EXIST);
        } else {
            $this->instances[$signature]->hydrate(array_intersect_key($entityFields, array_flip($structureFields)));
            $this->instances[$signature.'_COPY']->hydrate($this->instances[$signature]->fields());
        }

        // reconstituer une entité complète avec l'ensemble des données "annexes" et son statut
        $returnEntity = new (get_class($entity))();
        $returnEntity->hydrate($this->instances[$signature]->fields());
        $returnEntity->hydrate(array_diff_key($entityFields, array_flip($structureFields)));
        $returnEntity->status($this->instances[$signature]->status());
        $this->instances[$signature.'_COPY'] = $returnEntity;

        return $returnEntity;
    }

    /** Flush instances from the identity mapper.*/
    public function clear(): IdentityMapper
    {
        $this->instances = [];

        return $this;
    }
}

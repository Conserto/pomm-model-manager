<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PommProject\ModelManager\Converter;

use PommProject\Foundation\Converter\ConverterInterface;
use PommProject\Foundation\Converter\PgArray;
use PommProject\Foundation\Converter\PgBoolean;
use PommProject\Foundation\Exception\ConverterException;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Exception\ModelException;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;
use PommProject\ModelManager\Model\HydrationPlan;
use PommProject\ModelManager\Model\IdentityMapper;
use PommProject\ModelManager\Model\Projection;
use PommProject\ModelManager\Model\RowStructure;

/**
 * Entity converter. It handles row types and composite types.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 *
 * @template T of FlexibleEntityInterface
 */
class PgEntity implements ConverterInterface
{
    protected IdentityMapper $identityMapper;

    /**
     * @param class-string<T> $flexibleEntityClass
     * @param RowStructure $rowStructure
     * @param IdentityMapper|null $identityMapper
     */
    public function __construct(
        protected string $flexibleEntityClass,
        protected RowStructure $rowStructure,
        ?IdentityMapper $identityMapper = null
    ) {
        $this->identityMapper = $identityMapper ?? new IdentityMapper();
    }

    /**
     * Embeddable entities are converted here.
     *
     * @throws FoundationException
     * @throws ModelException
     * @see ConverterInterface
     *
     * @return T|null
     */
    public function fromPg(?string $data, string $type, Session $session): ?FlexibleEntityInterface
    {
        if (empty($data)) {
            return null;
        }

        $data = trim($data, '()');

        $projection = new Projection(
            $this->flexibleEntityClass,
            $this->rowStructure->getDefinition()
        );

        $hydrationPlan = new HydrationPlan(
            $projection,
            $session
        );

        $entity = $hydrationPlan->hydrate($this->transformData($data, $projection, $hydrationPlan));

        return $this->cacheEntity($entity);
    }

    /**
     * Split data into an array prefixed with field names.
     *
     * @return array<string, mixed>
     */
    private function transformData(string $data, Projection $projection, HydrationPlan $hydrationPlan): array
    {
        $outValues = json_decode($data, true);

        // Détecte si on est en JSON, permet de convertir les résultats de json_build_object
        $isJson = json_last_error() === JSON_ERROR_NONE;

        if ($isJson) {
            // Si on est en JSON, les booléens et les tableaux sont déjà bien convertis
            $hydrationPlan->removeConverter(PgBoolean::class);
            $hydrationPlan->removeConverter(PgArray::class);
        }  else {
            $values = str_getcsv($data, escape: "\\" );
            $definition = $projection->getFieldNames();
            $outValues = [];
            $valuesCount = count($values);

            for ($index = 0; $index < $valuesCount; $index++) {
                $fieldName = $definition[$index];
                $outValues[$fieldName] = preg_match(':^{.*}$:', (string) $values[$index])
                    ? stripcslashes((string) $values[$index])
                    : $values[$index]
                ;
            }
        }

        return $outValues;
    }

    /**
     * Check entity against the cache
     * @return T
     */
    public function cacheEntity(FlexibleEntityInterface $entity): FlexibleEntityInterface
    {
        return $this
            ->identityMapper
            ->fetch($entity, $this->rowStructure->getPrimaryKey());
    }

    /**
     * @throws ConverterException
     * @throws FoundationException
     * @throws ModelException
     * @see ConverterInterface
     * @param T|array|null $data
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        $fields = $this->getFields($data);
        $hydrationPlan = $this->createHydrationPlan($session);

        return sprintf(
            "row(%s)::%s",
            implode(',', $hydrationPlan->dry($fields)),
            $type
        );
    }

    /**
     * Create a new hydration plan.
     *
     * @throws FoundationException
     * @throws ModelException
     */
    protected function createHydrationPlan(Session $session): HydrationPlan
    {
        return new HydrationPlan(
            new Projection($this->flexibleEntityClass, $this->rowStructure->getDefinition()),
            $session
        );
    }

    /**
     * Return the fields array.
     *
     * @throws ConverterException
     * @return array<string, mixed>
     */
    protected function getFields(array|FlexibleEntityInterface $data): array
    {
        if (is_array($data)) {
            $fields = $data;
        } else {
            $this->checkData($data);
            $fields = $data->fields();
        }

        return $fields;
    }

    /**
     * Check if the given data is the right entity.
     *
     * @param T $data
     * @return PgEntity
     * @throws ConverterException
     */
    protected function checkData(FlexibleEntityInterface $data): PgEntity
    {
        if (!$data instanceof $this->flexibleEntityClass) {
            throw new ConverterException(
                sprintf(
                    "This converter only knows how to convert entities of type '%s' ('%s' given).",
                    $this->flexibleEntityClass,
                    $data::class
                )
            );
        }

        return $this;
    }

    /**
     * @see ConverterInterface
     *
     * @param T|array|null $data
     * @param string $type
     * @param Session $session
     * @return string|null
     *
     * @throws ConverterException
     * @throws FoundationException
     * @throws ModelException
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        if ($data === null) {
            return null;
        }

        $fields = $this->getFields($data);

        return
            sprintf("(%s)",
                implode(',', array_map(function ($val) {
                    $returned = $val;

                    if ($val === null) {
                        $returned = '';
                    } elseif ($val === '') {
                        $returned = '""';
                    } elseif (preg_match('/[,\s]/', $val)) {
                        $returned = sprintf('"%s"', str_replace('"', '""', $val));
                    }

                    return $returned;
                }, $this->createHydrationPlan($session)->freeze($fields)
                ))
            );
    }
}

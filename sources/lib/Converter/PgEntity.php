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
 * Entity converter.
 * It handles row types and composite types.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgEntity implements ConverterInterface
{
    protected IdentityMapper $identityMapper;

    public function __construct(
        protected string $flexibleEntityClass,
        protected RowStructure $rowStructure,
        IdentityMapper $identityMapper = null
    ) {
        $this->identityMapper = $identityMapper ?? new IdentityMapper();
    }

    /**
     * Embeddable entities are converted here.
     *
     * @throws FoundationException
     * @throws ModelException
     * @see ConverterInterface
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

        $entity = (new HydrationPlan($projection, $session))
            ->hydrate($this->transformData($data, $projection));

        return $this->cacheEntity($entity);
    }

    /** Split data into an array prefixed with field names. */
    private function transformData(string $data, Projection $projection): array
    {
        $values = str_getcsv($data);
        $definition = $projection->getFieldNames();
        $outValues = [];
        $valuesCount = count($values);

        for ($index = 0; $index < $valuesCount; $index++) {
            $outValues[$definition[$index]] = preg_match(':^{.*}$:', $values[$index])
                ? stripcslashes($values[$index])
                : $values[$index];
        }

        return $outValues;
    }

    /** Check entity against the cache. */
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
            join(',', $hydrationPlan->dry($fields)),
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
     */
    protected function getFields(mixed $data): array
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
     * @throws  ConverterException
     */
    protected function checkData(mixed $data): PgEntity
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
     * @throws ConverterException
     * @throws FoundationException
     * @throws ModelException
     * @see ConverterInterface
     */
    public function toPgStandardFormat(mixed $data, string $type, Session $session): ?string
    {
        if ($data === null) {
            return null;
        }

        $fields = $this->getFields($data);

        return
            sprintf("(%s)",
                join(',', array_map(function ($val) {
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

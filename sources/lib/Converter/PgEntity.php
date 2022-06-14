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
use PommProject\Foundation\Session\Session;
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;
use PommProject\ModelManager\Model\HydrationPlan;
use PommProject\ModelManager\Model\IdentityMapper;
use PommProject\ModelManager\Model\Projection;
use PommProject\ModelManager\Model\RowStructure;

/**
 * PgEntity
 *
 * Entity converter.
 * It handles row types and composite types.
 *
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ConverterInterface
 */
class PgEntity implements ConverterInterface
{
    protected IdentityMapper $identity_mapper;


    /**
     * Constructor.
     *
     * @access public
     * @param                $flexible_entity_class
     * @param RowStructure $row_structure
     * @param IdentityMapper|null $identity_mapper
     */
    public function __construct(
        protected $flexible_entity_class,
        protected RowStructure $row_structure,
        IdentityMapper $identity_mapper = null
    ) {
        $this->identity_mapper          = $identity_mapper ?? new IdentityMapper()
            ;
    }

    /**
     * fromPg
     *
     * Embeddable entities are converted here.
     *
     * @see ConverterInterface
     */
    public function fromPg(?string $data, string $type, Session $session): ?FlexibleEntityInterface
    {
        if (empty($data)) {
            return null;
        }

        $data = trim($data, '()');

        $projection = new Projection(
            $this->flexible_entity_class,
            $this->row_structure->getDefinition()
        );

        $entity = (new HydrationPlan(
            $projection,
            $session
        ))->hydrate($this->transformData($data, $projection));

        return $this->cacheEntity($entity);
    }

    /**
     * transformData
     *
     * Split data into an array prefixed with field names.
     *
     * @access private
     * @param string $data
     * @param  Projection   $projection
     * @return array
     */
    private function transformData(string $data, Projection $projection): array
    {
        $values         = str_getcsv($data);
        $definition     = $projection->getFieldNames();
        $out_values     = [];
        $values_count   = count($values);

        for ($index = 0; $index < $values_count; $index++) {
            $out_values[$definition[$index]] = preg_match(':^{.*}$:', (string) $values[$index])
                ? stripcslashes($values[$index])
                : $values[$index]
                ;
        }

        return $out_values;
    }

    /**
     * cacheEntity
     *
     * Check entity against the cache.
     *
     * @access public
     * @param  FlexibleEntityInterface   $entity
     * @return FlexibleEntityInterface
     */
    public function cacheEntity(FlexibleEntityInterface $entity): FlexibleEntityInterface
    {
        return $this
            ->identity_mapper
            ->fetch($entity, $this->row_structure->getPrimaryKey())
            ;
    }

    /**
     * toPg
     *
     * @see ConverterInterface
     */
    public function toPg(mixed $data, string $type, Session $session): string
    {
        if ($data === null) {
            return sprintf("NULL::%s", $type);
        }

        $fields = $this->getFields($data);
        $hydration_plan = $this->createHydrationPlan($session);

        return sprintf(
            "row(%s)::%s",
            join(',', $hydration_plan->dry($fields)),
            $type
        );
    }

    /**
     * createHydrationPlan
     *
     * Create a new hydration plan.
     *
     * @access protected
     * @param  Session          $session
     * @return HydrationPlan
     */
    protected function createHydrationPlan(Session $session): HydrationPlan
    {
        return new HydrationPlan(
            new Projection($this->flexible_entity_class, $this->row_structure->getDefinition()),
            $session
        );
    }

    /**
     * getFields
     *
     * Return the fields array.
     *
     * @access protected
     * @param mixed $data
     * @return array
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
     * checkData
     *
     * Check if the given data is the right entity.
     *
     * @access protected
     * @param  mixed        $data
     * @throws  ConverterException
     * @return PgEntity     $this
     */
    protected function checkData(mixed $data): PgEntity
    {
        if (!$data instanceof $this->flexible_entity_class) {
            throw new ConverterException(
                sprintf(
                    "This converter only knows how to convert entities of type '%s' ('%s' given).",
                    $this->flexible_entity_class,
                    $data::class
                )
            );
        }

        return $this;
    }

    /**
     * toPgStandardFormat
     *
     * @throws ConverterException
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
                    if ($val === null) {
                        return '';
                    } elseif ($val === '') {
                        return '""';
                    } elseif (preg_match('/[,\s]/', $val)) {
                        return sprintf('"%s"', str_replace('"', '""', $val));
                    } else {
                        return $val;
                    }
                }, $this->createHydrationPlan($session)->freeze($fields)
                ))
            );
    }
}

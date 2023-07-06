<?php
/*
 * This file is part of Pomm's ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Generator;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Inflector;
use PommProject\Foundation\ParameterHolder;
use PommProject\Foundation\Where;
use PommProject\ModelManager\Exception\GeneratorException;

/**
 * Generate a new model file.
 * If the given file already exist, it needs the force option to be set at
 * 'yes'.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class ModelGenerator extends BaseGenerator
{
    /**
     * Generate structure file.
     *
     * @throws GeneratorException|FoundationException
     * @see BaseGenerator
     */
    public function generate(ParameterHolder $input, array $output = []): array
    {
        $schemaOid = $this
            ->getSession()
            ->getInspector()
            ->getSchemaOid($this->schema);

        if ($schemaOid === null) {
            throw new GeneratorException(sprintf("Schema '%s' does not exist.", $this->schema));
        }

        $relationsInfo = $this
            ->getSession()
            ->getInspector()
            ->getSchemaRelations($schemaOid, new Where('cl.relname = $*', [$this->relation]))
            ;

        if ($relationsInfo->isEmpty()) {
            throw new GeneratorException(sprintf("Relation '%s.%s' does not exist.", $this->schema, $this->relation));
        }

        $this
            ->checkOverwrite($input)
            ->outputFileCreation($output)
            ->saveFile(
                $this->filename,
                $this->mergeTemplate(
                    [
                        'entity'        => Inflector::studlyCaps($this->relation),
                            'namespace'     => trim($this->namespace, '\\'),
                            'trait'         => $relationsInfo->current()['type'] === 'table'
                                ? 'WriteQueries' : 'ReadQueries',
                            'relation_type' => $relationsInfo->current()['type'],
                            'relation'      => $this->relation
                        ]
                    )
                );

        return $output;
    }

    /** @see BaseGenerator */
    protected function getCodeTemplate(): string
    {
        return <<<'__WRAP'
<?php

namespace {:namespace:};

use PommProject\ModelManager\Model\Model;
use PommProject\ModelManager\Model\ModelTrait\{:trait:};
use {:namespace:}\AutoStructure\{:entity:} as {:entity:}Structure;

/**
 * Model class for {:relation_type:} {:relation:}.
 *
 * @see Model
 * @extends Model<{:entity:}>
 */
class {:entity:}Model extends Model
{
    /** @use {:trait:}<{:entity:}> */
    use {:trait:};

    public function __construct()
    {
        $this->structure = new {:entity:}Structure;
        $this->flexibleEntityClass = {:entity:}::class;
    }
}

__WRAP;
    }
}

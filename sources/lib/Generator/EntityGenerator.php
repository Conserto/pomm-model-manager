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

use PommProject\Foundation\Inflector;
use PommProject\Foundation\ParameterHolder;
use PommProject\ModelManager\Exception\GeneratorException;

/**
 * Entity generator.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       BaseGenerator
 */
class EntityGenerator extends BaseGenerator
{
    /**
     * Generate Entity file.
     *
     * @throws GeneratorException
     * @see BaseGenerator
     */
    public function generate(ParameterHolder $input, array $output = []): array
    {
        $this
            ->checkOverwrite($input)
            ->outputFileCreation($output)
            ->saveFile(
                $this->filename,
                $this->mergeTemplate(
                    [
                        'namespace' => $this->namespace,
                        'entity'    => Inflector::studlyCaps($this->relation),
                        'relation'  => $this->relation,
                        'schema'    => $this->schema,
                        'flexible_container' => $this->flexibleContainer,
                        'flexible_container_class' => array_reverse(explode('\\', (string) $this->flexibleContainer))[0]
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

use {:flexible_container:};

/**
 * Flexible entity for relation
 * {:schema:}.{:relation:}
 *
 * @see FlexibleEntity
 */
class {:entity:} extends {:flexible_container_class:}
{
}

__WRAP;
    }
}

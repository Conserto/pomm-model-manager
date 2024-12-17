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

use PommProject\Foundation\ConvertedResultIterator;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Inflector;
use PommProject\Foundation\ParameterHolder;
use PommProject\ModelManager\Exception\GeneratorException;

/**
 * Generate a RowStructure file from relation inspection.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 */
class StructureGenerator extends BaseGenerator
{
    /**
     * Generate structure file.
     *
     * @throws GeneratorException|FoundationException
     * @see BaseGenerator
     */
    public function generate(ParameterHolder $input, array $output = []): array
    {
        $tableOid          = $this->checkRelationInformation();
        $fieldInformation = $this->getFieldInformation($tableOid);
        $primaryKey        = $this->getPrimaryKey($tableOid);
        $tableComment      = $this->getTableComment($tableOid);

        if (null === $tableComment) {
            $tableComment = <<<TEXT

Class and fields comments are inspected from table and fields comments.Just add comments in your database and they will appear here.
@see http://www.postgresql.org/docs/9.0/static/sql-comment.html
TEXT;
        }

        $this
            ->outputFileCreation($output)
            ->saveFile(
                $this->filename,
                $this->mergeTemplate(
                    [
                        'namespace'      => $this->namespace,
                        'class_name'     => $input->getParameter('class_name', Inflector::studlyCaps($this->relation)),
                        'relation'       => sprintf("%s.%s", $this->schema, $this->relation),
                        'primary_key'    => implode(
                            ', ',
                            array_map(
                                fn($val): string => sprintf("'%s'", $val),
                                $primaryKey
                            )
                        ),
                        'add_fields'     => $this->formatAddFields($fieldInformation),
                        'table_comment'  => $this->createPhpDocBlockFromText($tableComment),
                        'fields_comment' => $this->formatFieldsComment($fieldInformation),
                    ]
                )
            );

        return $output;
    }

    /** Format 'addField' method calls. */
    protected function formatAddFields(ConvertedResultIterator $fieldInformation): string
    {
        $strings = [];

        foreach ($fieldInformation as $info) {
            if (preg_match('/^(?:(.*)\.)?_(.*)$/', (string) $info['type'], $matches)) {
                if ($matches[1] !== '') {
                    $info['type'] = sprintf("%s.%s[]", $matches[1], $matches[2]);
                } else {
                    $info['type'] = $matches[2].'[]';
                }
            }

            $strings[] = sprintf("            ->addField('%s', '%s')", $info['name'], $info['type']);
        }

        return implode("\n", $strings);
    }

    /**
     * Format fields comment to be in the class comment. This is because there
     * can be very long comments or comments with carriage returns. It is
     * furthermore more convenient to get all the descriptions in the head of
     * the generated class.
     */
    protected function formatFieldsComment(ConvertedResultIterator $fieldInformation): string
    {
        $comments = [];
        foreach ($fieldInformation as $info) {
            if ($info['comment'] === null) {
                continue;
            }

            $comments[] = sprintf(" * %s:", $info['name']);
            $comments[] = $this->createPhpDocBlockFromText($info['comment']);
        }

        return empty($comments) ? ' *' : implode("\n", $comments);
    }

    /** Format a text into a PHPDoc comment block. */
    protected function createPhpDocBlockFromText(string $text): string
    {
        return implode(
            "\n",
            array_map(
                fn($line): string => ' * '.$line,
                explode("\n", wordwrap($text))
            )
        );
    }

    /**
     * Check if the given schema and relation exist. If so, the table oid is
     * returned, otherwise a GeneratorException is thrown.
     *
     * @throws GeneratorException|FoundationException
     */
    private function checkRelationInformation(): int
    {
        if ($this->getInspector()->getSchemaOid($this->schema) === null) {
            throw new GeneratorException(sprintf("Schema '%s' not found.", $this->schema));
        }

        $tableOid = $this->getInspector()->getTableOid($this->schema, $this->relation);

        if ($tableOid === null) {
            throw new GeneratorException(
                sprintf(
                    "Relation '%s' could not be found in schema '%s'.",
                    $this->relation,
                    $this->schema
                )
            );
        }

        return $tableOid;
    }

    /**
     * Fetch a table field information.
     *
     * @throws GeneratorException|FoundationException
     */
    protected function getFieldInformation(int $tableOid): ConvertedResultIterator
    {
        $fieldsInfo = $this->getInspector()->getTableFieldInformation($tableOid);

        if ($fieldsInfo === null) {
            throw new GeneratorException(
                sprintf("Error while fetching fields information for table oid '%s'.", $tableOid)
            );
        }

        return $fieldsInfo;
    }

    /**
     * Return the primary key of a relation if any.
     *
     * @throws FoundationException
     */
    protected function getPrimaryKey(int $tableOid): array
    {
        return $this->getInspector()->getPrimaryKey($tableOid);
    }

    /**
     * Grab table comment from database.
     *
     * @throws FoundationException
     */
    protected function getTableComment(int $tableOid): ?string
    {
        return $this->getInspector()->getTableComment($tableOid);
    }

    /**
     * getCodeTemplate
     *
     * @see BaseGenerator
     */
    protected function getCodeTemplate(): string
    {
        return <<<'__WRAP'
<?php
/**
 * This file has been automatically generated by Pomm's generator.
 * You MIGHT NOT edit this file as your changes will be lost at next
 * generation.
 */

namespace {:namespace:};

use PommProject\ModelManager\Model\RowStructure;

/**
 * Structure class for relation {:relation:}.
{:table_comment:}
 *
{:fields_comment:}
 *
 * @see RowStructure
 */
class {:class_name:} extends RowStructure
{
    public function __construct()
    {
        $this
            ->setRelation('{:relation:}')
            ->setPrimaryKey([{:primary_key:}])
{:add_fields:}
            ;
    }
}

__WRAP;
    }
}

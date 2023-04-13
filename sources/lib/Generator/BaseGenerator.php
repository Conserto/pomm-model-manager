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
use PommProject\Foundation\Inspector\Inspector;
use PommProject\Foundation\ParameterHolder;
use PommProject\ModelManager\Exception\GeneratorException;
use PommProject\ModelManager\Session;

/**
 * Base class for Generator
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @abstract
 */
abstract class BaseGenerator
{
    public function __construct(
        private Session $session,
        protected string $schema,
        protected string $relation,
        protected string $filename,
        protected string $namespace,
        protected ?string $flexibleContainer = null
    )
    {
    }

    /** Output what the generator will do. */
    protected function outputFileCreation(array &$output): BaseGenerator
    {
        $output[] = [
            'status' => 'ok',
            'operation' => file_exists($this->filename) ? 'overwriting' : 'creating',
            'file' => $this->filename
        ];

        return $this;
    }

    /** Set the session. */
    protected function setSession(Session $session): BaseGenerator
    {
        $this->session = $session;

        return $this;
    }

    /** Return the session is set. Throw an exception otherwise. */
    protected function getSession(): Session
    {
        return $this->session;
    }

    /**
     * Shortcut to session's inspector client.
     *
     * @throws FoundationException
     */
    protected function getInspector(): Inspector
    {
        /** @var Inspector $inspector */
        $inspector = $this->getSession()->getClientUsingPooler('inspector', null);
        return $inspector;
    }

    /**
     * Called to generate the file.
     * Possible options are:
     * - force: true if files can be overwritten, false otherwise
     */
    abstract public function generate(ParameterHolder $input, array $output = []): array;

    /** Return the code template for files to be generated. */
    abstract protected function getCodeTemplate(): string;

    /** Merge templates with given values. */
    protected function mergeTemplate(array $variables): string
    {
        $preparedVariables = [];
        foreach ($variables as $name => $value) {
            $preparedVariables[sprintf("{:%s:}", $name)] = $value;
        }

        return strtr(
            $this->getCodeTemplate(),
            $preparedVariables
        );
    }

    /**
     * Write the generated content to a file.
     *
     * @throws GeneratorException
     */
    protected function saveFile(string $filename, string $content): BaseGenerator
    {
        if (!file_exists(dirname($filename))
            && mkdir(dirname($filename), 0777, true) === false
        ) {
            throw new GeneratorException(
                sprintf("Could not create directory '%s'.", dirname($filename))
            );
        }

        if (file_put_contents($filename, $content) === false) {
            throw new GeneratorException(
                sprintf("Could not open '%s' for writing.", $filename)
            );
        }

        return $this;
    }

    /**
     * Check if the file exists and if the write is forced.
     *
     * @throws GeneratorException
     */
    protected function checkOverwrite(ParameterHolder $input): BaseGenerator
    {
        if (file_exists($this->filename) && $input->getParameter('force') !== true) {
            throw new GeneratorException(
                sprintf("Cannot overwrite file '%s' without --force option.", $this->filename)
            );
        }

        return $this;
    }
}

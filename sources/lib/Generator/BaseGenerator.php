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
 * BaseGenerator
 *
 * Base class for Generator
 *
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @abstract
 */
abstract class BaseGenerator
{
    /**
     * Constructor
     *
     * @access public
     * @param  Session $session
     * @param string $schema
     * @param string $relation
     * @param string $filename
     * @param string $namespace
     * @param          $flexible_container
     */
    public function __construct(
        private Session $session,
        protected string $schema,
        protected string $relation,
        protected string $filename,
        protected string $namespace,
        protected $flexible_container = null
    )
    {
    }

    /**
     * outputFileCreation
     *
     * Output what the generator will do.
     *
     * @access protected
     * @param  array            $output
     * @return BaseGenerator    $this
     */
    protected function outputFileCreation(array &$output): BaseGenerator
    {
        if (file_exists($this->filename)) {
            $output[] = ['status' => 'ok', 'operation' => 'overwriting', 'file' => $this->filename];
        } else {
            $output[] = ['status' => 'ok', 'operation' => 'creating', 'file' => $this->filename];
        }

        return $this;
    }

    /**
     * setSession
     *
     * Set the session.
     *
     * @access protected
     * @param  Session       $session
     * @return BaseGenerator $this
     */
    protected function setSession(Session $session): BaseGenerator
    {
        $this->session = $session;

        return $this;
    }

    /**
     * getSession
     *
     * Return the session is set. Throw an exception otherwise.
     *
     * @access protected
     * @return Session
     */
    protected function getSession(): Session
    {
        return $this->session;
    }

    /**
     * getInspector
     *
     * Shortcut to session's inspector client.
     *
     * @access protected
     * @return Inspector
     * @throws GeneratorException
     * @throws FoundationException
     */
    protected function getInspector(): Inspector
    {
        /** @var Inspector $inspector */
        $inspector = $this->getSession()->getClientUsingPooler('inspector', null);
        return $inspector;
    }

    /**
     * generate
     *
     * Called to generate the file.
     * Possible options are:
     * * force: true if files can be overwritten, false otherwise
     *
     * @access public
     * @param  ParameterHolder    $input
     * @param  array              $output
     * @throws GeneratorException
     * @return array              $output
     */
    abstract public function generate(ParameterHolder $input, array $output = []): array;

    /**
     * getCodeTemplate
     *
     * Return the code template for files to be generated.
     *
     * @access protected
     * @return string
     */
    abstract protected function getCodeTemplate(): string;

    /**
     * mergeTemplate
     *
     * Merge templates with given values.
     *
     * @access protected
     * @param  array  $variables
     * @return string
     */
    protected function mergeTemplate(array $variables): string
    {
        $prepared_variables = [];
        foreach ($variables as $name => $value) {
            $prepared_variables[sprintf("{:%s:}", $name)] = $value;
        }

        return strtr(
            $this->getCodeTemplate(),
            $prepared_variables
        );
    }

    /**
     * saveFile
     *
     * Write the generated content to a file.
     *
     * @access protected
     * @param string $filename
     * @param string $content
     * @return BaseGenerator $this
     *@throws GeneratorException
     */
    protected function saveFile(string $filename, string $content): BaseGenerator
    {
        if (!file_exists(dirname($filename))
            && mkdir(dirname($filename), 0777, true) === false
        ) {
            throw new GeneratorException(
                sprintf(
                    "Could not create directory '%s'.",
                    dirname($filename)
                )
            );
        }

        if (file_put_contents($filename, $content) === false) {
            throw new GeneratorException(
                sprintf(
                    "Could not open '%s' for writing.",
                    $filename
                )
            );
        }

        return $this;
    }

    /**
     * checkOverwrite
     *
     * Check if the file exists and if it the write is forced.
     *
     * @access protected
     * @param  ParameterHolder    $input
     * @throws GeneratorException
     * @return BaseGenerator      $this
     */
    protected function checkOverwrite(ParameterHolder $input): BaseGenerator
    {
        if (file_exists($this->filename) && $input->getParameter('force') !== true) {
            throw new GeneratorException(sprintf("Cannot overwrite file '%s' without --force option.", $this->filename));
        }

        return $this;
    }
}

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

use PommProject\Foundation\Client\ClientInterface;
use PommProject\Foundation\Client\ClientPooler;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\Foundation\Exception\FoundationException;
use PommProject\ModelManager\Exception\ModelException;

/**
 * ModelPooler
 *
 * Client pooler for model package.
 *
 * @package   ModelManager
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ClientPooler
 */
class ModelPooler extends ClientPooler
{
    /**
     * @see ClientPoolerInterface
     */
    public function getPoolerType(): string
    {
        return 'model';
    }

    /**
     * getClientFromPool
     *
     * @param string $identifier
     * @return ClientInterface|null
     * @throws FoundationException
     * @see    ClientPooler
     */
    protected function getClientFromPool(string $identifier): ?ClientInterface
    {
        return $this->getSession()->getClient($this->getPoolerType(), trim($identifier, "\\"));
    }

    /**
     * createModel
     *
     * @param object|string $identifier
     * @return Model
     * @throws ModelException if incorrect
     * @see    ClientPooler
     */
    protected function createClient(object|string $identifier): Model
    {
        try {
            $reflection = new \ReflectionClass($identifier);
        } catch (\ReflectionException $e) {
            throw new ModelException(sprintf(
                "Could not instantiate Model class '%s'. (Reason: '%s').",
                $identifier,
                $e->getMessage()
            ));
        }

        if (!$reflection->implementsInterface(ClientInterface::class)) {
            throw new ModelException(sprintf("'%s' class does not implement the ClientInterface interface.", $identifier));
        }

        if (!$reflection->isSubclassOf(Model::class)) {
            throw new ModelException(sprintf("'%s' class does not extend \PommProject\ModelManager\Model.", $identifier));
        }

        return new $identifier();
    }
}

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
use PommProject\ModelManager\Model\FlexibleEntity\FlexibleEntityInterface;

/**
 * Client pooler for model package.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ClientPooler
 */
class ModelPooler extends ClientPooler
{
    /** @see ClientPoolerInterface */
    public function getPoolerType(): string
    {
        return 'model';
    }

    /**
     * @throws FoundationException
     * @see    ClientPooler
     */
    protected function getClientFromPool(string $identifier): ?ClientInterface
    {
        return $this->getSession()->getClient($this->getPoolerType(), trim($identifier, "\\"));
    }

    /**
     * @see    ClientPooler
     * @param class-string<Model<FlexibleEntityInterface>> $identifier
     * @return Model<FlexibleEntityInterface>
     * @throws ModelException if incorrect
     */
    protected function createClient(string $identifier): Model
    {
        if (!class_exists($identifier)) {
            throw new ModelException(sprintf(
                "Could not instantiate Model class '%s'. (Reason: class does not exist).",
                $identifier
            ));
        }

        /** @var \ReflectionClass<Model<FlexibleEntityInterface>> $reflection */
        $reflection = new \ReflectionClass($identifier);

        if (!$reflection->implementsInterface(ClientInterface::class)) {
            throw new ModelException(
                sprintf("'%s' class does not implement the ClientInterface interface.", $identifier)
            );
        }

        if (!$reflection->isSubclassOf(Model::class)) {
            throw new ModelException(
                sprintf("'%s' class does not extend \PommProject\ModelManager\Model.", $identifier)
            );
        }

        return new $identifier();
    }
}

<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\ModelLayer;

use PommProject\Foundation\Client\ClientPooler;
use PommProject\Foundation\Client\ClientPoolerInterface;
use PommProject\ModelManager\Exception\ModelLayerException;

/**
 * Pooler for ModelLayer session client.
 *
 * @copyright 2014 - 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see       ClientPooler
 */
class ModelLayerPooler extends ClientPooler
{
    /** @see ClientPoolerInterface */
    public function getPoolerType(): string
    {
        return 'model_layer';
    }

    /**
     * @throws ModelLayerException
     * @see    ClientPooler
     */
    protected function createClient(string $identifier): ModelLayer
    {
        try {
            $reflection = new \ReflectionClass($identifier);
            if (!$reflection->isSubclassOf(ModelLayer::class)) {
                throw new ModelLayerException(sprintf("Class '%s' is not a subclass of ModelLayer.", $identifier));
            }
        } catch (\ReflectionException $e) {
            throw new ModelLayerException(
                sprintf("Error while loading class '%s' (%s).", $identifier, $e->getMessage())
            );
        }

        return new $identifier();
    }
}

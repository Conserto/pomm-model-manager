<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager;

use PommProject\Foundation\Exception\FoundationException;
use PommProject\Foundation\Session as FoundationSession;
use PommProject\ModelManager\Model\Model;
use PommProject\ModelManager\ModelLayer\ModelLayer;

/**
 * Model manager's session. It adds proxy method to use model manager's poolers.
 *
 * @copyright 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @see FoundationSession
 */
class Session extends FoundationSession
{
    /**
     * Return a model instance
     *
     * @throws FoundationException
     * @template T of Model
     * @param class-string<T> $class
     * @return T
     */
    public function getModel(string $class): Model
    {
        /** @var Model $modelManager */
        $modelManager = $this->getClientUsingPooler('model', $class);
        return $modelManager;
    }

    /**
     * Return a model layer instance
     *
     * @throws FoundationException
     * @template T of ModelLayer
     * @param class-string<T> $class
     * @return T
     */
    public function getModelLayer(string $class): ModelLayer
    {
        /** @var ModelLayer $modelLayerManager */
        $modelLayerManager = $this->getClientUsingPooler('model_layer', $class);
        return $modelLayerManager;
    }
}

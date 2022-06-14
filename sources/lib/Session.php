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
 * Session
 *
 * Model manager's session.
 * It adds proxy method to use model manager's poolers.
 *
 * @package   ModelManager
 * @copyright 2015 Grégoire HUBERT
 * @author    Grégoire HUBERT
 * @license   X11 {@link http://opensource.org/licenses/mit-license.php}
 *
 * @see FoundationSession
 */
class Session extends FoundationSession
{
    /**
     * getModel
     *
     * Return a model instance
     *
     * @access public
     * @param string $class
     * @return Model
     * @throws FoundationException
     */
    public function getModel(string $class): Model
    {
        /** @var Model $modelManager */
        $modelManager = $this->getClientUsingPooler('model', $class);
        return $modelManager;
    }

    /**
     * getModelLayer
     *
     * Return a model layer instance
     *
     * @access public
     * @param string $class
     * @return ModelLayer
     * @throws FoundationException
     */
    public function getModelLayer(string $class): ModelLayer
    {
        /** @var ModelLayer $modelLayerManager */
        $modelLayerManager = $this->getClientUsingPooler('model_layer', $class);
        return $modelLayerManager;
    }
}

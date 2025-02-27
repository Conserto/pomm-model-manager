<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2015 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model\FlexibleEntity;

/**
 * Entities with the ability to keep record of their modification or
 * persistence status.
 *
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Grégoire HUBERT
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         FlexibleEntityInterface
 */
trait StatefulEntityTrait
{
    private int $status = FlexibleEntityInterface::STATUS_NONE;

    /**
     * @see FlexibleEntityInterface
     */
    public function status(?int $status = null): int|FlexibleEntityInterface
    {
        if ($status !== null) {
            $this->status = $status;

            return $this;
        }

        return $this->status;
    }

    /** Set the entity as modified. */
    public function touch(): FlexibleEntityInterface
    {
        $this->status |= FlexibleEntityInterface::STATUS_MODIFIED;

        return $this;
    }
}

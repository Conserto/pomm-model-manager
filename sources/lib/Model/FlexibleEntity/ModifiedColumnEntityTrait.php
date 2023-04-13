<?php
/*
 * This file is part of the PommProject/ModelManager package.
 *
 * (c) 2014 - 2016 Grégoire HUBERT <hubert.greg@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace PommProject\ModelManager\Model\FlexibleEntity;

/**
 * @copyright   2014 - 2015 Grégoire HUBERT
 * @author      Mikael Paris
 * @license     X11 {@link http://opensource.org/licenses/mit-license.php}
 * @see         FlexibleEntityInterface
 */
trait ModifiedColumnEntityTrait
{
    private array $modifiedColumns = [];

    public function getModifiedColumns(): array
    {
        return $this->modifiedColumns;
    }

    public function addModifiedColumn(string $column): FlexibleEntityInterface
    {
        if (!in_array($column, $this->modifiedColumns)) {
            $this->modifiedColumns[] = $column;
        }

        return $this;
    }

    public function removeModifiedColumn(string $column): FlexibleEntityInterface
    {
        $key = array_search($column, $this->modifiedColumns, true);

        if ($key !== false) {
            unset($this->modifiedColumns[$key]);
        }

        return $this;
    }
}

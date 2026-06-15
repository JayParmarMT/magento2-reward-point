<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Config\Backend;

use Magento\Framework\App\Config\Value;

/**
 * Backend model for multiselect config fields that need to store values as comma-separated strings.
 *
 * Magento's default Config\Value does not handle array values from multiselect fields —
 * it would serialize PHP's "Array" string instead of the actual values.
 * This model implodes the array to a comma-separated string before saving,
 * and explodes it back to an array after loading.
 */
class ArraySerialized extends Value
{
    /**
     * Implode array value to comma-separated string before saving.
     *
     * @return $this
     */
    public function beforeSave(): static
    {
        $value = $this->getValue();

        if (is_array($value)) {
            $this->setValue(implode(',', $value));
        }

        return parent::beforeSave();
    }

    /**
     * Explode comma-separated string back to array after loading.
     *
     * @return $this
     */
    public function afterLoad(): static
    {
        $value = $this->getValue();

        if (is_string($value) && $value !== '') {
            $this->setValue(explode(',', $value));
        }

        return parent::afterLoad();
    }
}

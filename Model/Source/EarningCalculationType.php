<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Earning Calculation Type source model
 */
class EarningCalculationType implements OptionSourceInterface
{
    public const BEFORE_TAX = 'before_tax';
    public const AFTER_TAX = 'after_tax';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BEFORE_TAX, 'label' => __('Before Tax')],
            ['value' => self::AFTER_TAX, 'label' => __('After Tax')],
        ];
    }

    /**
     * Get options as key-value array
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            self::BEFORE_TAX => __('Before Tax'),
            self::AFTER_TAX => __('After Tax'),
        ];
    }
}

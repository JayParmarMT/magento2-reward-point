<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Discount Action source model for spending rules
 */
class DiscountAction implements OptionSourceInterface
{
    public const BY_FIXED = 'by_fixed';
    public const BY_PERCENT = 'by_percent';
    public const FREE_SHIPPING = 'free_shipping';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BY_FIXED, 'label' => __('Fixed Amount Discount')],
            ['value' => self::BY_PERCENT, 'label' => __('Percentage Discount')],
            ['value' => self::FREE_SHIPPING, 'label' => __('Free Shipping')],
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
            self::BY_FIXED => __('Fixed Amount Discount'),
            self::BY_PERCENT => __('Percentage Discount'),
            self::FREE_SHIPPING => __('Free Shipping'),
        ];
    }
}

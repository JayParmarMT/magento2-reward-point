<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Spending Action source model
 */
class SpendingAction implements OptionSourceInterface
{
    public const FIXED_POINTS = 'fixed_points';
    public const PER_POINTS = 'per_points';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIXED_POINTS, 'label' => __('Fixed Number of Points')],
            ['value' => self::PER_POINTS, 'label' => __('Points per X Discount')],
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
            self::FIXED_POINTS => __('Fixed Number of Points'),
            self::PER_POINTS => __('Points per X Discount'),
        ];
    }
}

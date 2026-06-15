<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Tier Basis source model — defines what metric determines tier switching
 */
class TierBasis implements OptionSourceInterface
{
    public const EARNED_POINTS = 'earned_points';
    public const SPENT_AMOUNT = 'spent_amount';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::EARNED_POINTS, 'label' => __('Earned Points')],
            ['value' => self::SPENT_AMOUNT, 'label' => __('Spent Amount')],
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
            self::EARNED_POINTS => __('Earned Points'),
            self::SPENT_AMOUNT => __('Spent Amount'),
        ];
    }
}

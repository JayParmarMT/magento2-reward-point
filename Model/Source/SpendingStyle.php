<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Spending Style source model
 */
class SpendingStyle implements OptionSourceInterface
{
    public const FLEXIBLE = 'flexible';
    public const FIXED = 'fixed';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FLEXIBLE, 'label' => __('Flexible (Customer Chooses)')],
            ['value' => self::FIXED, 'label' => __('Fixed (Auto Apply)')],
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
            self::FLEXIBLE => __('Flexible (Customer Chooses)'),
            self::FIXED => __('Fixed (Auto Apply)'),
        ];
    }
}

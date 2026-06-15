<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Action Type source model for earning rules
 */
class ActionType implements OptionSourceInterface
{
    public const FIXED = 'fixed';
    public const PER_PRICE = 'per_price';
    public const PER_PROFIT = 'per_profit';
    public const PER_QTY = 'per_qty';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIXED, 'label' => __('Fixed Points')],
            ['value' => self::PER_PRICE, 'label' => __('Points per Price Amount')],
            ['value' => self::PER_PROFIT, 'label' => __('Points per Profit Amount')],
            ['value' => self::PER_QTY, 'label' => __('Points per Quantity')],
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
            self::FIXED => __('Fixed Points'),
            self::PER_PRICE => __('Points per Price Amount'),
            self::PER_PROFIT => __('Points per Profit Amount'),
            self::PER_QTY => __('Points per Quantity'),
        ];
    }
}

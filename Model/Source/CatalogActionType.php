<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Action Type source model for catalog earning rules
 */
class CatalogActionType implements OptionSourceInterface
{
    public const FIXED = 'fixed';
    public const PER_PRICE = 'per_price';
    public const PER_PROFIT = 'per_profit';

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
        ];
    }
}

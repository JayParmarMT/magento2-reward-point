<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Max Points Type source model
 */
class MaxPointsType implements OptionSourceInterface
{
    public const FIXED = 'fixed';
    public const PERCENT = 'percent';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIXED, 'label' => __('Fixed Number of Points')],
            ['value' => self::PERCENT, 'label' => __('Percent of Order Total')],
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
            self::FIXED => __('Fixed Number of Points'),
            self::PERCENT => __('Percent of Order Total'),
        ];
    }
}

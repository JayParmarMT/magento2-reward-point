<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Rounding Method source model
 */
class RoundingMethod implements OptionSourceInterface
{
    public const NORMAL = 'normal';
    public const UP = 'up';
    public const DOWN = 'down';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::NORMAL, 'label' => __('Normal (Standard Rounding)')],
            ['value' => self::UP, 'label' => __('Round Up')],
            ['value' => self::DOWN, 'label' => __('Round Down')],
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
            self::NORMAL => __('Normal (Standard Rounding)'),
            self::UP => __('Round Up'),
            self::DOWN => __('Round Down'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Point Label Position source model
 */
class PointLabelPosition implements OptionSourceInterface
{
    public const BEFORE = 'before';
    public const AFTER = 'after';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::BEFORE, 'label' => __('Before Amount (e.g. Points 100)')],
            ['value' => self::AFTER, 'label' => __('After Amount (e.g. 100 Points)')],
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
            self::BEFORE => __('Before Amount (e.g. Points 100)'),
            self::AFTER => __('After Amount (e.g. 100 Points)'),
        ];
    }
}

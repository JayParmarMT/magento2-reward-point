<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * URL Key Mode source model for referral links
 */
class UrlKeyMode implements OptionSourceInterface
{
    public const PARAM = 'param';
    public const HASH = 'hash';

    /**
     * Get options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::PARAM, 'label' => __('Query Parameter (?ref=CODE)')],
            ['value' => self::HASH, 'label' => __('Hash Fragment (#ref=CODE)')],
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
            self::PARAM => __('Query Parameter (?ref=CODE)'),
            self::HASH => __('Hash Fragment (#ref=CODE)'),
        ];
    }
}

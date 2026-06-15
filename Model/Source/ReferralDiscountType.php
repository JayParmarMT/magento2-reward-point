<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for Referral Rule discount type options
 */
class ReferralDiscountType implements OptionSourceInterface
{
    public const FIXED = 'fixed';
    public const PERCENT = 'percent';

    /**
     * Return option array
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FIXED, 'label' => __('Fixed Amount')],
            ['value' => self::PERCENT, 'label' => __('Percentage')],
        ];
    }
}

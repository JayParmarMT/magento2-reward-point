<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for Social Sharing — Pages to Display config field.
 */
class SocialPages implements OptionSourceInterface
{
    /**
     * Return available page options.
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'referral', 'label' => __('Referral Page')],
            ['value' => 'account',  'label' => __('My Account / Dashboard')],
            ['value' => 'product',  'label' => __('Product Page')],
        ];
    }
}

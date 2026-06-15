<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for tier email template options
 */
class TierEmailTemplate implements OptionSourceInterface
{
    /**
     * Return list of tier email template options
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('-- Use Default (Tier Upgrade Template) --')],
            [
                'value' => 'meetanshi_rewardpoints_email_tier_upgrade_template',
                'label' => __('Reward Points: Tier Upgrade Notification'),
            ],
            [
                'value' => 'meetanshi_rewardpoints_email_tier_downgrade_template',
                'label' => __('Reward Points: Tier Downgrade Notification'),
            ],
        ];
    }
}

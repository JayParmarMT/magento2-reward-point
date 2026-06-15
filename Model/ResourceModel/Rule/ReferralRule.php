<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Rule;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Referral Rule Resource Model
 */
class ReferralRule extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_referral_rule';
    public const PRIMARY_KEY = 'rule_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }
}

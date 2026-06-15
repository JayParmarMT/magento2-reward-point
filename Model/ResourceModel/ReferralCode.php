<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Referral Code Resource Model
 */
class ReferralCode extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_referral_code';
    public const PRIMARY_KEY = 'code_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }
}

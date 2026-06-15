<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Tier History Resource Model
 */
class TierHistory extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_tier_history';
    public const PRIMARY_KEY = 'history_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }
}

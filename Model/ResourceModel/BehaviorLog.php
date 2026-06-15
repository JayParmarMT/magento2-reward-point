<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Behavior Log Resource Model
 */
class BehaviorLog extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_behavior_log';
    public const PRIMARY_KEY = 'log_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }
}

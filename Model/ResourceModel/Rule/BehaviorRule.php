<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Rule;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Behavior Rule Resource Model
 */
class BehaviorRule extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_behavior_rule';
    public const PRIMARY_KEY = 'rule_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }
}

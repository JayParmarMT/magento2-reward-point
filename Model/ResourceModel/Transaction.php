<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Transaction Resource Model
 */
class Transaction extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_transaction';
    public const PRIMARY_KEY = 'transaction_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Transaction;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\Transaction;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;

/**
 * Reward Points Transaction Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Transaction::class, TransactionResource::class);
    }
}

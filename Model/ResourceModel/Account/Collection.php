<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Account;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\Account;
use Meetanshi\RewardPoints\Model\ResourceModel\Account as AccountResource;

/**
 * Reward Points Account Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Account::class, AccountResource::class);
    }
}

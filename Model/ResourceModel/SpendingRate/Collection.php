<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\SpendingRate;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate as SpendingRateResource;

/**
 * Reward Points Spending Rate Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SpendingRate::class, SpendingRateResource::class);
    }
}

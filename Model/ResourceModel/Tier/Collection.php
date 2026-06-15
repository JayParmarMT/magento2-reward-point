<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Tier;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\Tier;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier as TierResource;

/**
 * Reward Points Tier Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Tier::class, TierResource::class);
    }
}

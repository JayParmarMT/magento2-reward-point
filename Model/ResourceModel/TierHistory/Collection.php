<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\TierHistory;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\TierHistory;
use Meetanshi\RewardPoints\Model\ResourceModel\TierHistory as TierHistoryResource;

/**
 * Reward Points Tier History Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TierHistory::class, TierHistoryResource::class);
    }
}

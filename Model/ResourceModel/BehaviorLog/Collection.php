<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\BehaviorLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\BehaviorLog;
use Meetanshi\RewardPoints\Model\ResourceModel\BehaviorLog as BehaviorLogResource;

/**
 * Reward Points Behavior Log Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(BehaviorLog::class, BehaviorLogResource::class);
    }
}

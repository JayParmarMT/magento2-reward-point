<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\EarningRate;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\EarningRate;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate as EarningRateResource;

/**
 * Reward Points Earning Rate Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(EarningRate::class, EarningRateResource::class);
    }
}

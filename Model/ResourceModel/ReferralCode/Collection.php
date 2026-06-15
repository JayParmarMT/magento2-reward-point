<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\ReferralCode;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode as ReferralCodeResource;

/**
 * Reward Points Referral Code Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ReferralCode::class, ReferralCodeResource::class);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Invitation;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\Invitation;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation as InvitationResource;

/**
 * Reward Points Invitation Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(Invitation::class, InvitationResource::class);
    }
}

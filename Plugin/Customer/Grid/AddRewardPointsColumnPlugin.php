<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Customer\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Meetanshi\RewardPoints\Model\ResourceModel\Account;

/**
 * Plugin to join reward points balance to customer grid collection
 */
class AddRewardPointsColumnPlugin
{
    /**
     * @param Account $accountResource
     */
    public function __construct(
        private readonly Account $accountResource,
    ) {
    }

    /**
     * Join reward points account data to customer grid collection
     *
     * @param SearchResult $subject
     * @return SearchResult
     */
    public function afterGetSearchResult(SearchResult $subject): SearchResult
    {
        if ($subject->getMainTable() !== $subject->getConnection()->getTableName('customer_grid_flat')) {
            return $subject;
        }

        if (!$subject->getFlag('reward_points_joined')) {
            $accountTable = $this->accountResource->getMainTable();

            $subject->getSelect()->joinLeft(
                ['rp_account' => $accountTable],
                'main_table.entity_id = rp_account.customer_id',
                ['reward_points_balance' => 'COALESCE(rp_account.points_balance, 0)'],
            );

            $subject->setFlag('reward_points_joined', true);
        }

        return $subject;
    }
}

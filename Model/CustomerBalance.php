<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\CustomerBalanceInterface;

/**
 * Customer balance service — resolves website from store context for REST /me endpoint
 */
class CustomerBalance implements CustomerBalanceInterface
{
    /**
     * @param BalanceManagementInterface $balanceManagement
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Get the authenticated customer's reward points balance.
     * Website is resolved automatically from the current store context.
     *
     * @param int $customerId
     * @return int
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getMyBalance(int $customerId): int
    {
        $websiteId = (int) $this->storeManager->getStore()->getWebsiteId();

        return $this->balanceManagement->getBalance($customerId, $websiteId);
    }
}

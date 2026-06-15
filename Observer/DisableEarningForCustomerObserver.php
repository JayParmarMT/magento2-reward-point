<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;

/**
 * Observer to prevent earning when reward account is disabled
 *
 * Hooks into a pre-earn event and checks account.is_enabled.
 * If the account is disabled, throws LocalizedException to halt earning.
 */
class DisableEarningForCustomerObserver implements ObserverInterface
{
    /**
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
    ) {
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     * @throws LocalizedException
     */
    public function execute(Observer $observer): void
    {
        $event = $observer->getEvent();
        $customerId = (int) $event->getCustomerId();

        if (!$customerId) {
            return;
        }

        try {
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);

            if (!$account->isEnabled()) {
                throw new LocalizedException(
                    __('Reward points earning is disabled for this account.'),
                );
            }
        } catch (NoSuchEntityException) {
            // No account means no restriction
        }
    }
}

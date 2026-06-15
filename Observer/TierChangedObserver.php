<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Helper\Email as EmailHelper;
use Psr\Log\LoggerInterface;

/**
 * Observer for tier change events — sends notification email
 */
class TierChangedObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param EmailHelper $emailHelper
     * @param AccountRepositoryInterface $accountRepository
     * @param TierRepositoryInterface $tierRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly EmailHelper $emailHelper,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $customerId = (int) $observer->getData('customer_id');
        $websiteId = (int) $observer->getData('website_id');
        $oldTierId = $observer->getData('old_tier_id');
        $newTierId = $observer->getData('new_tier_id');
        $changeType = (string) $observer->getData('change_type');

        if (!$this->config->isEnabled()) {
            return;
        }

        if ($customerId <= 0 || $newTierId === null) {
            return;
        }

        $this->logger->info(
            'RewardPoints: Tier changed',
            [
                'customer_id' => $customerId,
                'website_id'  => $websiteId,
                'old_tier_id' => $oldTierId,
                'new_tier_id' => $newTierId,
                'change_type' => $changeType,
            ],
        );

        if (!$this->config->isEmailNotificationEnabled() || !$this->config->isEmailOnTierChange()) {
            return;
        }

        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $this->populateAccountCustomerData($account, $customerId);

            $newTier = $this->tierRepository->getById((int) $newTierId);
            $oldTier = $oldTierId !== null ? $this->tierRepository->getById((int) $oldTierId) : null;

            $storeId = $this->resolveStoreId($websiteId);
            $isUpgrade = in_array($changeType, ['up', 'upgrade', 'initial'], true);

            $this->emailHelper->sendTierChange($account, $oldTier, $newTier, $isUpgrade, $storeId);
        } catch (LocalizedException $e) {
            $this->logger->warning(
                'RewardPoints: TierChangedObserver email failed (LocalizedException)',
                ['message' => $e->getMessage()],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: TierChangedObserver email unexpected error',
                ['exception' => $e],
            );
        }
    }

    /**
     * Populate account object with customer email and name so EmailHelper can read them
     *
     * @param \Meetanshi\RewardPoints\Api\Data\AccountInterface $account
     * @param int $customerId
     * @return void
     */
    private function populateAccountCustomerData(
        \Meetanshi\RewardPoints\Api\Data\AccountInterface $account,
        int $customerId,
    ): void {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $account->setData('customer_email', $customer->getEmail());
            $account->setData('customer_firstname', $customer->getFirstname());
            $account->setData('customer_lastname', $customer->getLastname());
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: TierChangedObserver could not load customer data',
                ['customer_id' => $customerId, 'message' => $e->getMessage()],
            );
        }
    }

    /**
     * Resolve a default store ID for the given website
     *
     * @param int $websiteId
     * @return int
     */
    private function resolveStoreId(int $websiteId): int
    {
        try {
            $website = $this->storeManager->getWebsite($websiteId);
            $defaultStore = $website->getDefaultStore();

            return $defaultStore ? (int) $defaultStore->getId() : 1;
        } catch (\Exception $e) {
            return 1;
        }
    }
}

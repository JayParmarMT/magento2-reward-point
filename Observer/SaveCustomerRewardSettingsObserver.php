<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\App\RequestInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;

/**
 * Observer to save reward points account settings from customer admin edit form
 *
 * On customer save in admin, reads reward_points POST data and updates account flags.
 */
class SaveCustomerRewardSettingsObserver implements ObserverInterface
{
    /**
     * @param AccountRepositoryInterface $accountRepository
     * @param RequestInterface $request
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
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
        $rewardData = $this->request->getPost('reward_points');

        if (!is_array($rewardData)) {
            return;
        }

        $customer = $observer->getEvent()->getCustomer();

        if (!$customer) {
            return;
        }

        $customerId = (int) $customer->getId();

        if (!$customerId) {
            return;
        }

        try {
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);

            $changed = false;

            if (isset($rewardData['is_enabled'])) {
                $account->setIsEnabled((bool) $rewardData['is_enabled']);
                $changed = true;
            }

            if (isset($rewardData['is_subscribed_balance'])) {
                $account->setIsSubscribedBalance((bool) $rewardData['is_subscribed_balance']);
                $changed = true;
            }

            if (isset($rewardData['is_subscribed_expiration'])) {
                $account->setIsSubscribedExpiration((bool) $rewardData['is_subscribed_expiration']);
                $changed = true;
            }

            if ($changed) {
                $this->accountRepository->save($account);
            }
        } catch (NoSuchEntityException) {
            // Account does not exist yet — no action needed
        } catch (\Exception) {
            // Silently fail rather than blocking customer save
        }
    }
}

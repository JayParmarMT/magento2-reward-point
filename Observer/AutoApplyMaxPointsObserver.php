<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\Quote;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\CartManagementInterface;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\SpendingCalculator;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Psr\Log\LoggerInterface;

/**
 * Auto-applies the maximum redeemable points to a cart when
 * Config::isUseMaxByDefault() is enabled and the customer has not yet
 * manually applied / removed points in the current session.
 *
 * Triggered by checkout_cart_save_after (fires on every cart save,
 * including the initial add-to-cart and quantity updates).
 */
class AutoApplyMaxPointsObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param AccountRepositoryInterface $accountRepository
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SpendingCalculator $spendingCalculator
     * @param CartManagementInterface $cartManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SpendingCalculator $spendingCalculator,
        private readonly CartManagementInterface $cartManagement,
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
        try {
            /** @var Quote $quote */
            $quote = $observer->getData('cart')?->getQuote();

            if (!$quote) {
                return;
            }

            $storeId = (int) $quote->getStoreId();

            if (!$this->config->isEnabled($storeId) || !$this->config->isUseMaxByDefault($storeId)) {
                return;
            }

            $customerId = (int) $quote->getCustomerId();

            if ($customerId === 0) {
                return;
            }

            // Do not override if the customer has already applied points (non-zero)
            if ((int) $quote->getData('reward_points_used') > 0) {
                return;
            }

            $websiteId = (int) $quote->getStore()->getWebsiteId();

            try {
                $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            } catch (\Exception) {
                return;
            }

            if (!$account->isEnabled()) {
                return;
            }

            $availableBalance = (int) $account->getPointsBalance();

            if ($availableBalance <= 0) {
                return;
            }

            $maxPoints = $this->resolveMaxApplicablePoints($availableBalance, $websiteId, $storeId);

            if ($maxPoints <= 0) {
                return;
            }

            $this->cartManagement->applyPoints((string) $quote->getId(), $maxPoints);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: AutoApplyMaxPointsObserver failed',
                ['message' => $e->getMessage()],
            );
        }
    }

    /**
     * Determine the maximum number of points to auto-apply.
     *
     * Respects getMaxSpendingPoints() flat cap and the customer's available balance.
     * Does NOT apply the percentage cap here — that is enforced inside CartManagement.
     *
     * @param int $availableBalance
     * @param int $websiteId
     * @param int $storeId
     * @return int
     */
    private function resolveMaxApplicablePoints(int $availableBalance, int $websiteId, int $storeId): int
    {
        $flatCap = $this->config->getMaxSpendingPoints($storeId);
        $points  = $flatCap > 0 ? min($availableBalance, $flatCap) : $availableBalance;

        $minPoints = $this->config->getMinSpendingPoints($storeId);

        if ($points < $minPoints) {
            return 0;
        }

        return $points;
    }
}

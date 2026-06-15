<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\ViewModel\Account;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * ViewModel for the reward points account dashboard
 */
class Dashboard implements ArgumentInterface
{
    /**
     * @param SessionFactory $customerSessionFactory
     * @param AccountRepositoryInterface $accountRepository
     * @param TierRepositoryInterface $tierRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     */
    public function __construct(
        private readonly SessionFactory $customerSessionFactory,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
    ) {
    }

    /**
     * Resolve the customer's website ID.
     * Prefers the website stored on the customer record; falls back to the
     * current store's website so the page never throws on a null return.
     *
     * @return int
     */
    private function getSession(): Session
    {
        return $this->customerSessionFactory->create();
    }

    private function resolveWebsiteId(): int
    {
        $websiteId = (int) $this->getSession()->getCustomer()->getWebsiteId();

        if ($websiteId > 0) {
            return $websiteId;
        }

        try {
            return (int) $this->storeManager->getWebsite()->getId();
        } catch (\Exception) {
            return 1;
        }
    }

    /**
     * Get reward points account for current customer and website
     *
     * @return AccountInterface|null
     */
    public function getAccount(): ?AccountInterface
    {
        try {
            $customerId = (int) $this->getSession()->getCustomerId();
            $websiteId = $this->resolveWebsiteId();

            return $this->accountRepository->getOrCreate($customerId, $websiteId);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get current points balance
     *
     * @return int
     */
    public function getPointsBalance(): int
    {
        $account = $this->getAccount();

        return $account ? $account->getPointsBalance() : 0;
    }

    /**
     * Get total earned points
     *
     * @return int
     */
    public function getTotalEarned(): int
    {
        $account = $this->getAccount();

        return $account ? $account->getTotalEarned() : 0;
    }

    /**
     * Get total spent points
     *
     * @return int
     */
    public function getTotalSpent(): int
    {
        $account = $this->getAccount();

        return $account ? $account->getTotalSpent() : 0;
    }

    /**
     * Get customer's current tier
     *
     * @return TierInterface|null
     */
    public function getCurrentTier(): ?TierInterface
    {
        $account = $this->getAccount();

        if (!$account || !$account->getCurrentTierId()) {
            return null;
        }

        try {
            return $this->tierRepository->getById((int) $account->getCurrentTierId());
        } catch (NoSuchEntityException $e) {
            return null;
        }
    }

    /**
     * Get the next tier above the current one based on min_points
     *
     * @return TierInterface|null
     */
    public function getNextTier(): ?TierInterface
    {
        $balance = $this->getPointsBalance();

        try {
            $sortOrder = $this->sortOrderBuilder
                ->setField(TierInterface::MIN_POINTS)
                ->setAscendingDirection()
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TierInterface::IS_ACTIVE, 1)
                ->addFilter(TierInterface::MIN_POINTS, $balance, 'gt')
                ->setSortOrders([$sortOrder])
                ->setPageSize(1)
                ->create();

            $results = $this->tierRepository->getList($searchCriteria);
            $items = $results->getItems();

            return !empty($items) ? reset($items) : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get tier progress as a fraction 0.0–1.0
     *
     * @return float
     */
    public function getTierProgress(): float
    {
        $currentTier = $this->getCurrentTier();
        $nextTier = $this->getNextTier();

        if (!$nextTier) {
            return 1.0;
        }

        $balance = $this->getPointsBalance();
        $start = $currentTier ? $currentTier->getMinPoints() : 0;
        $end = $nextTier->getMinPoints();
        $range = $end - $start;

        if ($range <= 0) {
            return 1.0;
        }

        $progress = ($balance - $start) / $range;

        return (float) max(0.0, min(1.0, $progress));
    }

    /**
     * Get last 5 transactions for the current customer
     *
     * @return TransactionInterface[]
     */
    public function getRecentTransactions(): array
    {
        $account = $this->getAccount();

        if (!$account) {
            return [];
        }

        try {
            $sortOrder = $this->sortOrderBuilder
                ->setField(TransactionInterface::CREATED_AT)
                ->setDescendingDirection()
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TransactionInterface::ACCOUNT_ID, $account->getAccountId())
                ->setSortOrders([$sortOrder])
                ->setPageSize(5)
                ->create();

            $results = $this->transactionRepository->getList($searchCriteria);

            return array_values($results->getItems());
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Format points with the configured label
     *
     * @param int $points
     * @return string
     */
    public function formatPoints(int $points): string
    {
        return $this->config->formatPoints($points);
    }

    /**
     * Get earning rate display string
     *
     * @return string
     */
    public function getEarningRateDisplay(): string
    {
        return (string) __('Earn reward points on every purchase');
    }

    /**
     * Should social share buttons show on this page?
     *
     * @param string $page  'account' | 'product' | 'referral'
     * @return bool
     */
    public function isSocialEnabledOnPage(string $page): bool
    {
        $pages = $this->config->getSocialPages();
        return in_array($page, $pages, true);
    }

    /**
     * Show Facebook button?
     *
     * @return bool
     */
    public function isFacebookEnabled(): bool
    {
        return $this->config->isSocialFacebookEnabled();
    }

    /**
     * Show Twitter button?
     *
     * @return bool
     */
    public function isTwitterEnabled(): bool
    {
        return $this->config->isSocialTwitterEnabled();
    }

    /**
     * Show Pinterest button?
     *
     * @return bool
     */
    public function isPinterestEnabled(): bool
    {
        return $this->config->isSocialPinterestEnabled();
    }
}

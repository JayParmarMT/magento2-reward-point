<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Block for the Tier Milestones section on the account dashboard
 */
class Milestones extends Template
{
    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param AccountRepositoryInterface $accountRepository
     * @param TierRepositoryInterface $tierRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if this block should be rendered
     *
     * @return bool
     */
    public function canShow(): bool
    {
        return $this->config->isEnabled() && $this->config->isTierEnabled();
    }

    /**
     * Get all active tiers sorted by min_points ascending
     *
     * @return TierInterface[]
     */
    public function getTiers(): array
    {
        try {
            $sortOrder = $this->sortOrderBuilder
                ->setField(TierInterface::MIN_POINTS)
                ->setAscendingDirection()
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TierInterface::IS_ACTIVE, 1)
                ->setSortOrders([$sortOrder])
                ->create();

            $results = $this->tierRepository->getList($searchCriteria);

            return array_values($results->getItems());
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get customer's current points balance
     *
     * @return int
     */
    public function getCustomerPoints(): int
    {
        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);

            return $account->getPointsBalance();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get the current tier for the customer
     *
     * @return TierInterface|null
     */
    public function getCurrentTier(): ?TierInterface
    {
        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);

            if (!$account->getCurrentTierId()) {
                return null;
            }

            return $this->tierRepository->getById((int) $account->getCurrentTierId());
        } catch (NoSuchEntityException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get the next tier above the customer's current balance
     *
     * @return TierInterface|null
     */
    public function getNextTier(): ?TierInterface
    {
        $balance = $this->getCustomerPoints();

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
     * Get points still needed to reach the next tier
     *
     * @return int
     */
    public function getPointsForNextTier(): int
    {
        $nextTier = $this->getNextTier();

        if (!$nextTier) {
            return 0;
        }

        return max(0, $nextTier->getMinPoints() - $this->getCustomerPoints());
    }

    /**
     * Get progress bar background colour from config
     *
     * @return string
     */
    public function getProgressBgColor(): string
    {
        $color = $this->config->getTierProgressBgColor();

        return $color !== '' ? $color : '#e0e0e0';
    }

    /**
     * Get progress bar fill colour from config
     *
     * @return string
     */
    public function getProgressColor(): string
    {
        $color = $this->config->getTierProgressColor();

        return $color !== '' ? $color : '#4CAF50';
    }
}

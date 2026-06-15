<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\ViewModel\Header;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Magento\Framework\Api\SearchCriteriaBuilder;

/**
 * ViewModel for the header balance chip and top-links balance display
 */
class Balance implements ArgumentInterface
{
    /**
     * @param SessionFactory $customerSessionFactory
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param PriceCurrencyInterface $priceCurrency
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly SessionFactory $customerSessionFactory,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    /**
     * Check if module is enabled and balance should be shown in top links
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        if (!$this->config->isShowTopLinks()) {
            return false;
        }

        if (!$this->isLoggedIn() && !$this->config->isShowForGuests()) {
            return false;
        }

        $balance = $this->getBalance();

        if ($balance === 0 && $this->config->isHideIfZero()) {
            return false;
        }

        return true;
    }

    /**
     * Check if balance should be shown in minicart
     *
     * @return bool
     */
    public function isEnabledInMinicart(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        if (!$this->config->isShowOnMinicart()) {
            return false;
        }

        if (!$this->isLoggedIn() && !$this->config->isShowForGuests()) {
            return false;
        }

        $balance = $this->getBalance();

        if ($balance === 0 && $this->config->isHideIfZero()) {
            return false;
        }

        return true;
    }

    /**
     * Check if current visitor is logged in
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->getSession()->isLoggedIn();
    }

    /**
     * Get the current customer's point balance (0 if not logged in or no account)
     *
     * @return int
     */
    public function getBalance(): int
    {
        if (!$this->isLoggedIn()) {
            return 0;
        }

        $customerId = (int) $this->getSession()->getCustomerId();
        $websiteId = $this->resolveWebsiteId();

        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);

            return $account->getPointsBalance();
        } catch (NoSuchEntityException) {
            return 0;
        } catch (\Exception) {
            return 0;
        }
    }

    /**
     * Format balance with point label (or as currency if configured)
     *
     * @return string
     */
    public function getFormattedBalance(): string
    {
        $balance = $this->getBalance();

        if ($this->config->isShowAsCurrency()) {
            $currencyValue = $this->getBalanceAsCurrency($balance);

            if ($currencyValue !== '') {
                return $currencyValue;
            }
        }

        return $this->config->formatPoints($balance);
    }

    /**
     * Convert a points balance to its currency equivalent using the best spending rate
     *
     * @param int $points
     * @return string  formatted currency string, or '' if no rate found
     */
    public function getBalanceAsCurrency(int $points): string
    {
        if ($points <= 0) {
            return '';
        }

        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SpendingRateInterface::IS_ACTIVE, 1)
                ->create();

            $results = $this->spendingRateRepository->getList($searchCriteria);
            $rates = $results->getItems();

            if (empty($rates)) {
                return '';
            }

            usort($rates, static fn ($a, $b) => $a->getPriority() <=> $b->getPriority());
            $rate = reset($rates);

            $ratePoints = (int) $rate->getPoints();
            $rateCurrency = (float) $rate->getCurrencyAmount();

            if ($ratePoints <= 0 || $rateCurrency <= 0.0) {
                return '';
            }

            $baseValue = ($points / $ratePoints) * $rateCurrency;
            $displayValue = $this->priceCurrency->convert($baseValue);

            return $this->priceCurrency->format(
                $displayValue,
                false,
                PriceCurrencyInterface::DEFAULT_PRECISION,
            );
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Get My Reward Points page URL
     *
     * @return string
     */
    public function getAccountUrl(): string
    {
        try {
            return $this->storeManager->getStore()->getUrl('rewardpoints/account');
        } catch (\Exception) {
            return '';
        }
    }

    /**
     * Get customer session
     *
     * @return Session
     */
    private function getSession(): Session
    {
        return $this->customerSessionFactory->create();
    }

    /**
     * Resolve current website ID
     *
     * @return int
     */
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
}

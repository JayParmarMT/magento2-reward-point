<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\SpendingCalculator;

/**
 * Reward points block for cart page
 */
class RewardPoints extends Template
{
    /**
     * @param Context $context
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param AccountRepositoryInterface $accountRepository
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SpendingCalculator $spendingCalculator
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param Config $config
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SpendingCalculator $spendingCalculator,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Config $config,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check if the module is enabled and the cart block should be shown
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isShowOnCart();
    }

    /**
     * Check if points balance should be shown as currency equivalent
     *
     * @return bool
     */
    public function isShowAsCurrency(): bool
    {
        return $this->config->isShowAsCurrency();
    }

    /**
     * Check if current visitor is a logged-in customer
     *
     * @return bool
     */
    public function isLoggedIn(): bool
    {
        return $this->customerSession->isLoggedIn();
    }

    /**
     * Get customer's current points balance
     *
     * @return int
     */
    public function getCustomerBalance(): int
    {
        if (!$this->customerSession->isLoggedIn()) {
            return 0;
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);

            return $account->getPointsBalance();
        } catch (NoSuchEntityException $e) {
            return 0;
        }
    }

    /**
     * Get minimum points required per order
     *
     * @return int
     */
    public function getMinPoints(): int
    {
        return $this->config->getMinSpendingPoints();
    }

    /**
     * Get maximum points that can be applied to this order
     *
     * @return int
     */
    public function getMaxPoints(): int
    {
        $balance = $this->getCustomerBalance();
        $configMax = $this->config->getMaxSpendingPoints();

        if ($configMax > 0) {
            return min($balance, $configMax);
        }

        return $balance;
    }

    /**
     * Get points currently applied to the quote
     *
     * @return int
     */
    public function getPointsApplied(): int
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            return (int) $quote->getData('reward_points_used');
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get current discount amount from applied points
     *
     * @return float
     */
    public function getDiscountAmount(): float
    {
        try {
            $quote = $this->checkoutSession->getQuote();

            return (float) $quote->getData('reward_points_discount');
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    /**
     * Get the currency equivalent of the given points using the active spending rate
     *
     * @param int $points
     * @return string  formatted currency string, or '' if no rate available
     */
    public function getCurrencyEquivalent(int $points): string
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
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get spending rate display string using the current store currency symbol.
     *
     * The rate's `currency_amount` is defined in base currency.  We convert it
     * to the display currency so that the label matches what the customer sees.
     *
     * @return string
     */
    public function getSpendingRateDisplay(): string
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SpendingRateInterface::IS_ACTIVE, 1)
                ->create();

            $results = $this->spendingRateRepository->getList($searchCriteria);
            $rates = $results->getItems();

            if (empty($rates)) {
                return '';
            }

            usort($rates, static fn($a, $b) => $a->getPriority() <=> $b->getPriority());
            $rate = reset($rates);

            $baseAmount    = (float) $rate->getCurrencyAmount();
            $displayAmount = $this->priceCurrency->convert($baseAmount);
            $formattedAmount = $this->priceCurrency->format($displayAmount, false, PriceCurrencyInterface::DEFAULT_PRECISION);

            return (string) __('%1 pts = %2', $rate->getPoints(), $formattedAmount);
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Get the discount amount formatted in the current store currency.
     *
     * Returns the display-currency value stored on the quote (reward_points_discount)
     * formatted with the active currency symbol.
     *
     * @return string
     */
    public function getFormattedDiscountAmount(): string
    {
        $amount = $this->getDiscountAmount();

        if ($amount <= 0.0) {
            return '';
        }

        return $this->priceCurrency->format($amount, false, PriceCurrencyInterface::DEFAULT_PRECISION);
    }

    /**
     * Check if the slider should be enabled (balance >= min)
     *
     * @return bool
     */
    public function isSliderEnabled(): bool
    {
        return $this->getCustomerBalance() >= $this->getMinPoints();
    }

    /**
     * Check if use max by default is configured
     *
     * @return bool
     */
    public function isUseMaxByDefault(): bool
    {
        return $this->config->isUseMaxByDefault();
    }

    /**
     * Format a points value using the configured label
     *
     * @param int $points
     * @return string
     */
    public function formatBalance(int $points): string
    {
        return $this->config->formatPoints($points);
    }

    /**
     * Get the apply URL
     *
     * @return string
     */
    public function getApplyUrl(): string
    {
        return $this->getUrl('rewardpoints/cart/apply');
    }

    /**
     * Get the remove URL
     *
     * @return string
     */
    public function getRemoveUrl(): string
    {
        return $this->getUrl('rewardpoints/cart/remove');
    }
}

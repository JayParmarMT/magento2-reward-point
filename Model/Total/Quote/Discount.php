<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Total\Quote;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\SpendingCalculator;
use Meetanshi\RewardPoints\Model\Rule\Validator\SpendingRuleConditionValidator;
use Psr\Log\LoggerInterface;

/**
 * Quote total collector for reward points discount.
 *
 * All internal calculations are performed in BASE currency (the same currency
 * as rate->getCurrencyAmount()).  The display/order-currency amount is derived
 * via PriceCurrencyInterface::convert() and stored separately so that
 * base_reward_points_discount always holds the base-currency value and
 * reward_points_discount always holds the order-currency value.
 *
 * Spending rule conditions are evaluated before applying the discount.  If at
 * least one active spending rule exists and none of their conditions match the
 * current quote, redemption is blocked (points are cleared).
 */
class Discount extends AbstractTotal
{
    private const TOTAL_CODE = 'reward_points';

    /**
     * @param Config $config
     * @param AccountRepositoryInterface $accountRepository
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SpendingCalculator $spendingCalculator
     * @param StoreManagerInterface $storeManager
     * @param PriceCurrencyInterface $priceCurrency
     * @param SpendingRuleConditionValidator $spendingRuleConditionValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SpendingCalculator $spendingCalculator,
        private readonly StoreManagerInterface $storeManager,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly SpendingRuleConditionValidator $spendingRuleConditionValidator,
        private readonly LoggerInterface $logger,
    ) {
        $this->setCode(self::TOTAL_CODE);
    }

    /**
     * Collect reward points discount total.
     *
     * Calculations are done in base currency; the display amount is converted
     * for the order-currency columns and for the totals segment shown to the customer.
     *
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return $this
     */
    public function collect(
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ) {
        parent::collect($quote, $shippingAssignment, $total);

        if (!$shippingAssignment->getItems()) {
            return $this;
        }

        if (!$this->config->isEnabled()) {
            return $this;
        }

        $pointsUsed = (int) $quote->getData('reward_points_used');

        if ($pointsUsed <= 0) {
            return $this;
        }

        // If "Spend from Coupon Orders" is disabled, block redemption when a coupon is applied.
        if (!$this->config->isSpendFromCouponOrders() && $quote->getCouponCode()) {
            $quote->setData('reward_points_used', 0);
            $quote->setData('reward_points_discount', 0.0);
            $quote->setData('base_reward_points_discount', 0.0);

            return $this;
        }

        // Validate spending rule conditions — if active spending rules exist but none
        // match this quote, block redemption silently (no error to customer; just clear).
        if (!$this->spendingRuleConditionValidator->hasMatchingRule($quote)) {
            $quote->setData('reward_points_used', 0);
            $quote->setData('reward_points_discount', 0.0);
            $quote->setData('base_reward_points_discount', 0.0);

            return $this;
        }

        try {
            $customerId = (int) $quote->getCustomerId();

            if (!$customerId) {
                return $this;
            }

            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account   = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $availableBalance = $account->getPointsBalance();

            if ($availableBalance <= 0) {
                $quote->setData('reward_points_used', 0);
                $quote->setData('reward_points_discount', 0.0);
                $quote->setData('base_reward_points_discount', 0.0);

                return $this;
            }

            $effectivePoints = min($pointsUsed, $availableBalance);
            $minPoints       = $this->config->getMinSpendingPoints();
            $maxPoints       = $this->config->getMaxSpendingPoints();

            if ($effectivePoints < $minPoints) {
                return $this;
            }

            if ($maxPoints > 0) {
                $effectivePoints = min($effectivePoints, $maxPoints);
            }

            $rate = $this->getApplicableSpendingRate($customerId, $websiteId);

            if (!$rate) {
                return $this;
            }

            // calculateDiscountForPoints() returns a BASE-currency amount
            // (rate->getCurrencyAmount() is stored in base currency).
            $baseDiscount = $this->spendingCalculator->calculateDiscountForPoints($effectivePoints, $rate);

            if ($baseDiscount <= 0) {
                return $this;
            }

            // Determine the base amount to cap the discount against.
            // When include_tax = Yes, the cap includes the base tax amount.
            // When spend_on_shipping = Yes, the cap includes base shipping.
            $baseSubtotal = (float) $total->getBaseTotalAmount('subtotal');

            if ($this->config->isIncludeTax()) {
                $baseSubtotal += (float) $total->getBaseTotalAmount('tax');
            }

            if ($this->config->isSpendOnShipping()) {
                $baseSubtotal += (float) $total->getBaseTotalAmount('shipping');
            }

            $baseDiscount = min($baseDiscount, $baseSubtotal);

            // Convert to order/display currency for the totals segment and display columns.
            $store          = $quote->getStore();
            $displayDiscount = $this->priceCurrency->convert($baseDiscount, $store);

            // Cap display discount against display subtotal (include tax/shipping if configured).
            $displaySubtotal = (float) $total->getTotalAmount('subtotal');

            if ($this->config->isIncludeTax()) {
                $displaySubtotal += (float) $total->getTotalAmount('tax');
            }

            if ($this->config->isSpendOnShipping()) {
                $displaySubtotal += (float) $total->getTotalAmount('shipping');
            }

            $displayDiscount = min($displayDiscount, $displaySubtotal);

            $quote->setData('reward_points_used', $effectivePoints);
            $quote->setData('reward_points_discount', $displayDiscount);
            $quote->setData('base_reward_points_discount', $baseDiscount);

            // setTotalAmount / setBaseTotalAmount must receive distinct values
            // when base ≠ display currency so that Magento's grand-total math is correct.
            $total->setTotalAmount(self::TOTAL_CODE, -$displayDiscount);
            $total->setBaseTotalAmount(self::TOTAL_CODE, -$baseDiscount);
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error collecting quote total',
                ['exception' => $e],
            );
        }

        return $this;
    }

    /**
     * Fetch total segment for cart/checkout display.
     *
     * The value shown to the customer is the display/order-currency discount.
     *
     * @param Quote $quote
     * @param Total $total
     * @return array<string, mixed>|null
     */
    public function fetch(Quote $quote, Total $total): ?array
    {
        $discount = (float) $quote->getData('reward_points_discount');

        if ($discount <= 0) {
            return null;
        }

        // Title MUST be a Phrase object — TotalsConverter only calls ->render() when is_object() is true.
        $label = $this->config->getDiscountLabel();
        $title = $label !== '' ? __('%1', $label) : __('Reward Points Discount');

        return [
            'code'  => self::TOTAL_CODE,
            'title' => $title,
            'value' => -$discount,
        ];
    }

    /**
     * Get the highest-priority active spending rate.
     *
     * @param int $customerId
     * @param int $websiteId
     * @return SpendingRateInterface|null
     */
    private function getApplicableSpendingRate(int $customerId, int $websiteId): ?SpendingRateInterface
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(SpendingRateInterface::IS_ACTIVE, 1)
                ->create();

            $results = $this->spendingRateRepository->getList($searchCriteria);
            $rates   = $results->getItems();

            if (empty($rates)) {
                return null;
            }

            usort($rates, static fn($a, $b) => $a->getPriority() <=> $b->getPriority());

            return reset($rates) ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}

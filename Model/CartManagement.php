<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\Quote;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\CartManagementInterface;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\SpendingCalculator;
use Meetanshi\RewardPoints\Model\Rule\Validator\SpendingRuleConditionValidator;
use Meetanshi\RewardPoints\Model\TierCalculator;
use Psr\Log\LoggerInterface;

/**
 * Cart Reward Points Management implementation.
 *
 * This class handles applying/removing reward points via the GraphQL / REST API
 * path.  It mirrors the logic in Model/Total/Quote/Discount.php — calculations
 * are performed in BASE currency and converted to the order/display currency
 * for storage and display so that multi-currency stores work correctly.
 *
 * Important: quote attribute keys must match exactly what Model/Total/Quote/Discount.php
 * reads (reward_points_used / reward_points_discount / base_reward_points_discount).
 * The previous implementation used different keys (meetanshi_reward_points /
 * meetanshi_reward_discount) which made the GraphQL apply path completely non-functional.
 */
class CartManagement implements CartManagementInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param AccountRepositoryInterface $accountRepository
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SpendingCalculator $spendingCalculator
     * @param PriceCurrencyInterface $priceCurrency
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     * @param TierCalculator $tierCalculator
     * @param SpendingRuleConditionValidator $spendingRuleConditionValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SpendingCalculator $spendingCalculator,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly TierCalculator $tierCalculator,
        private readonly SpendingRuleConditionValidator $spendingRuleConditionValidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Apply reward points to a cart.
     *
     * Sets reward_points_used on the quote, then triggers collectTotals() so
     * Model/Total/Quote/Discount.php recalculates the discount amount.
     *
     * @param string $cartId
     * @param int $points
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function applyPoints(string $cartId, int $points): bool
    {
        /** @var Quote $quote */
        $quote   = $this->cartRepository->get((int) $cartId);
        $storeId = (int) $quote->getStoreId();

        if (!$this->config->isEnabled($storeId)) {
            throw new LocalizedException(__('Reward points are not enabled.'));
        }

        $customerId = (int) $quote->getCustomerId();

        if ($customerId === 0) {
            throw new LocalizedException(__('Reward points require a logged-in customer.'));
        }

        $websiteId = (int) $quote->getStore()->getWebsiteId();
        $customerGroupId = (int) $quote->getCustomerGroupId();

        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
        } catch (NoSuchEntityException $e) {
            throw new LocalizedException(__('Reward points account not found.'));
        }

        if (!$account->isEnabled()) {
            throw new LocalizedException(__('Your reward points account is disabled.'));
        }

        $availableBalance = $account->getPointsBalance();

        if ($points > $availableBalance) {
            throw new LocalizedException(
                __('Insufficient reward points balance. Available: %1', $availableBalance),
            );
        }

        // Respect "Spend from Coupon Orders" setting
        if (!$this->config->isSpendFromCouponOrders($storeId) && $quote->getCouponCode()) {
            throw new LocalizedException(
                __('Reward points cannot be combined with a coupon code.'),
            );
        }

        // Validate spending rule conditions — if active rules exist and none match, block redemption.
        if (!$this->spendingRuleConditionValidator->hasMatchingRule($quote)) {
            throw new LocalizedException(
                __('Reward points cannot be applied: your cart does not meet the spending rule conditions.'),
            );
        }

        $minPoints = $this->config->getMinSpendingPoints($storeId);
        $maxPoints = $this->config->getMaxSpendingPoints($storeId);

        if ($points < $minPoints) {
            throw new LocalizedException(
                __('Minimum %1 points required to redeem.', $minPoints),
            );
        }

        $rate = $this->getApplicableSpendingRate($websiteId, $customerGroupId);

        if (!$rate) {
            throw new LocalizedException(__('No spending rate is configured.'));
        }

        // D-02: honour getMaxSpendType() — flat vs. percentage cap.
        $effectivePoints = $this->resolveMaxPoints($points, $maxPoints, $storeId, $quote, $rate);

        // D-07: apply tier spending discount (reduces points required for the same discount value).
        $effectivePoints = $this->tierCalculator->getSpendingDiscount(
            $effectivePoints,
            $customerId,
            $websiteId,
            $customerGroupId,
        );

        // calculateDiscountForPoints() returns a BASE-currency amount.
        $baseDiscount = $this->spendingCalculator->calculateDiscountForPoints($effectivePoints, $rate);

        // Convert to order/display currency for storage in the display column.
        $store           = $quote->getStore();
        $displayDiscount = $this->priceCurrency->convert($baseDiscount, $store);

        // D-03: cap against subtotal; include shipping only if configured.
        $baseSubtotal    = (float) $quote->getBaseSubtotal();
        $displaySubtotal = (float) $quote->getSubtotal();

        if ($this->config->isSpendOnShipping($storeId)) {
            $baseSubtotal    += (float) $quote->getBaseShippingAmount();
            $displaySubtotal += (float) $quote->getShippingAmount();
        }

        $baseDiscount    = min($baseDiscount, $baseSubtotal);
        $displayDiscount = min($displayDiscount, $displaySubtotal);

        // Set the points used; the discount amounts are computed by collectTotals()
        // via Model/Total/Quote/Discount.php.  We set them here as well so that they
        // are available immediately if collectTotals() is not called again before save.
        $quote->setData('reward_points_used', $effectivePoints);
        $quote->setData('reward_points_discount', $displayDiscount);
        $quote->setData('base_reward_points_discount', $baseDiscount);
        $quote->collectTotals();

        try {
            $this->cartRepository->save($quote);
        } catch (\Exception $e) {
            $this->logger->error('CartManagement::applyPoints save error: ' . $e->getMessage());

            throw new CouldNotSaveException(__('Could not apply reward points to cart.'));
        }

        return true;
    }

    /**
     * Remove reward points from a cart.
     *
     * @param string $cartId
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function removePoints(string $cartId): bool
    {
        /** @var Quote $quote */
        $quote = $this->cartRepository->get((int) $cartId);

        $quote->setData('reward_points_used', 0);
        $quote->setData('reward_points_discount', 0.0);
        $quote->setData('base_reward_points_discount', 0.0);
        $quote->collectTotals();

        try {
            $this->cartRepository->save($quote);
        } catch (\Exception $e) {
            $this->logger->error('CartManagement::removePoints save error: ' . $e->getMessage());

            throw new LocalizedException(__('Could not remove reward points from cart.'));
        }

        return true;
    }

    /**
     * Resolve the effective maximum points the customer may redeem for this cart.
     *
     * When getMaxSpendType() === 'percent', the ceiling is derived from the redeemable
     * order total: max_points_per_order is treated as a percentage of the base subtotal
     * (plus shipping if isSpendOnShipping), converted to points via the spending rate.
     * For the flat type (default), max_points_per_order is used directly.
     *
     * @param int $requestedPoints
     * @param int $maxPointsConfig
     * @param int $storeId
     * @param Quote $quote
     * @param SpendingRateInterface $rate
     * @return int
     */
    private function resolveMaxPoints(
        int $requestedPoints,
        int $maxPointsConfig,
        int $storeId,
        Quote $quote,
        SpendingRateInterface $rate,
    ): int {
        if ($this->config->getMaxSpendType($storeId) === 'percent' && $maxPointsConfig > 0) {
            // Compute the redeemable base total (subtotal ± shipping)
            $baseTotal = (float) $quote->getBaseSubtotal();

            if ($this->config->isSpendOnShipping($storeId)) {
                $baseTotal += (float) $quote->getBaseShippingAmount();
            }

            // How many points equal maxPointsConfig% of the redeemable total?
            $cappedDiscount = $baseTotal * ($maxPointsConfig / 100);
            $maxByPercent   = $this->spendingCalculator->calculatePointsForDiscount($cappedDiscount, $rate);

            return min($requestedPoints, $maxByPercent);
        }

        // Flat cap
        return $maxPointsConfig > 0 ? min($requestedPoints, $maxPointsConfig) : $requestedPoints;
    }

    /**
     * Get the highest-priority active spending rate eligible for the given website and customer group.
     *
     * A rate is eligible when:
     *  - It has no website restriction (no row in junction table), OR its rate_id appears in
     *    the website junction table for the given websiteId with rule_type = 'spending_rate'
     *  - It has no customer-group restriction (no row in junction table), OR its rate_id
     *    appears in the customer-group junction table for the given customerGroupId
     *
     * @param int $websiteId
     * @param int $customerGroupId
     * @return SpendingRateInterface|null
     */
    private function getApplicableSpendingRate(int $websiteId, int $customerGroupId): ?SpendingRateInterface
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

            $eligibleRateIds = $this->getEligibleSpendingRateIds(
                array_keys($rates),
                $websiteId,
                $customerGroupId,
            );

            $eligible = array_filter(
                $rates,
                static fn($rate) => in_array($rate->getId(), $eligibleRateIds, true),
            );

            if (empty($eligible)) {
                return null;
            }

            usort($eligible, static fn($a, $b) => $a->getPriority() <=> $b->getPriority());

            return reset($eligible) ?: null;
        } catch (\Exception $e) {
            $this->logger->error('CartManagement: getApplicableSpendingRate error', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Filter spending rate IDs by website and customer-group junction tables.
     *
     * A rate passes if:
     *  - No rows exist in the website junction for this rule_type → applies to all websites
     *  - OR the websiteId is listed for this rate
     * Same logic for customer group.
     *
     * @param array<int|string> $allRateIds
     * @param int $websiteId
     * @param int $customerGroupId
     * @return array<int|string>
     */
    private function getEligibleSpendingRateIds(
        array $allRateIds,
        int $websiteId,
        int $customerGroupId,
    ): array {
        if (empty($allRateIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $ruleType = 'spending_rate';

        // Rates that have AT LEAST ONE website restriction row
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $restrictedWebsiteIds = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['rule_id'])
                ->where('rule_type = ?', $ruleType)
                ->where('rule_id IN (?)', $allRateIds),
        );

        // Of those restricted rates, which ones allow this website?
        $allowedByWebsite = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['rule_id'])
                ->where('rule_type = ?', $ruleType)
                ->where('website_id = ?', $websiteId)
                ->where('rule_id IN (?)', $allRateIds),
        );

        // Rates that have AT LEAST ONE customer-group restriction row
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');
        $restrictedCgIds = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['rule_id'])
                ->where('rule_type = ?', $ruleType)
                ->where('rule_id IN (?)', $allRateIds),
        );

        // Of those restricted rates, which ones allow this customer group?
        $allowedByGroup = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['rule_id'])
                ->where('rule_type = ?', $ruleType)
                ->where('customer_group_id = ?', $customerGroupId)
                ->where('rule_id IN (?)', $allRateIds),
        );

        $eligible = [];

        foreach ($allRateIds as $rateId) {
            $websiteOk = !in_array($rateId, $restrictedWebsiteIds, true)
                || in_array($rateId, $allowedByWebsite, true);
            $groupOk = !in_array($rateId, $restrictedCgIds, true)
                || in_array($rateId, $allowedByGroup, true);

            if ($websiteOk && $groupOk) {
                $eligible[] = $rateId;
            }
        }

        return $eligible;
    }
}

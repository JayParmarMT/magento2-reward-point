<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Calculator;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Sales\Api\Data\OrderInterface;
use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;
use Meetanshi\RewardPoints\Api\EarningRateRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Rule\CartRule;
use Meetanshi\RewardPoints\Model\Rule\Validator\CartRuleConditionValidator;
use Psr\Log\LoggerInterface;

/**
 * Calculates how many points should be awarded for a given order.
 *
 * Earning priority:
 *  1. Per-item catalog rule index (pre-indexed into meetanshi_rewardpoints_catalog_rule_product)
 *  2. Cart earning rules whose conditions match the order — applied order-wide to the
 *     base subtotal when no catalog rule overrides the full order
 *  3. Fallback: active earning rate (rate × subtotal)
 *
 * Cart rules are evaluated AFTER catalog rules.  When at least one cart rule matches,
 * its points calculation replaces the rate-based fallback for items not already
 * covered by a catalog rule.
 */
class OrderEarningCalculator
{
    /**
     * @param Config $config
     * @param EarningRateRepositoryInterface $earningRateRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param EarningCalculator $earningCalculator
     * @param ResourceConnection $resourceConnection
     * @param CartRuleConditionValidator $cartRuleConditionValidator
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly EarningRateRepositoryInterface $earningRateRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly EarningCalculator $earningCalculator,
        private readonly ResourceConnection $resourceConnection,
        private readonly CartRuleConditionValidator $cartRuleConditionValidator,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Calculate points to award for an order.
     *
     * Per-item catalog rule points (with stop_rules_processing enforcement) are
     * calculated first and replace the item-level rate-based contribution when a
     * matching catalog rule exists.  Items with no catalog rule fall back to the
     * order-level earning rate.
     *
     * @param OrderInterface $order
     * @param int $websiteId
     * @param int $customerId
     * @return int
     */
    public function calculateOrderPoints(OrderInterface $order, int $websiteId, int $customerId): int
    {
        try {
            $storeId = (int) $order->getStoreId();
            $customerGroupId = (int) $order->getCustomerGroupId();

            // Warn when currency rates are not configured. Earning calculations always use
            // base_row_total (base currency = GBP), so the math is still correct, but a
            // store_to_base_rate of 0.0 means the merchant has not set up currency rates,
            // which typically causes order_currency amounts to equal base_currency amounts (£1 = $1).
            $baseCurrency  = (string) $order->getBaseCurrencyCode();
            $orderCurrency = (string) $order->getOrderCurrencyCode();

            if ($baseCurrency !== $orderCurrency && (float) $order->getStoreToBaseRate() == 0.0) {
                $this->logger->warning(
                    'RewardPoints: store_to_base_rate is 0 but base and order currencies differ. '
                    . 'Currency exchange rates may not be configured. '
                    . 'Reward points are calculated on base_row_total (base currency) so the point math is correct, '
                    . 'but order amounts may be stored at an incorrect exchange rate.',
                    [
                        'order_id'           => $order->getEntityId(),
                        'base_currency'      => $baseCurrency,
                        'order_currency'     => $orderCurrency,
                        'store_to_base_rate' => $order->getStoreToBaseRate(),
                    ],
                );
            }

            $rate = $this->getApplicableEarningRate($websiteId, $customerGroupId);

            // Evaluate which cart earning rules (if any) match this order's conditions.
            // Returns rules sorted by priority ASC; stop_rules_processing is respected
            // inside getMatchingRulesForOrder().
            $matchingCartRules = $this->cartRuleConditionValidator->getMatchingRulesForOrder($order);

            $totalPoints = 0;

            foreach ($order->getItems() as $item) {
                if ($item->getParentItemId()) {
                    // Skip child items (configurable/bundle children) — parent carries the price
                    continue;
                }

                $productId = (int) $item->getProductId();
                // Calculation type: 'before_tax' uses row total before tax (after discount);
                // 'after_tax' includes row tax in the earnable base (default).
                $calculationType = $this->config->getCalculationType($storeId);
                $baseRowTotal = (float) $item->getBaseRowTotal() - (float) $item->getBaseDiscountAmount();

                if ($calculationType === 'after_tax') {
                    $baseRowTotal += (float) $item->getBaseTaxAmount();
                }

                $itemPrice = $baseRowTotal;
                $itemQty   = max(1, (float) $item->getQtyOrdered());

                // Attempt catalog rule lookup for this product
                $catalogPoints = $this->getCatalogRulePointsForItem(
                    $productId,
                    $websiteId,
                    $customerGroupId,
                    $itemPrice,
                    $itemQty,
                );

                if ($catalogPoints !== null) {
                    $totalPoints += $catalogPoints;
                    continue;
                }

                // Cart earning rules — applied per-item for items not covered by a catalog rule.
                // When matching cart rules exist they take priority over the rate-based fallback.
                if (!empty($matchingCartRules)) {
                    $totalPoints += $this->calculateCartRulePointsForItem(
                        $matchingCartRules,
                        $itemPrice,
                        $itemQty,
                    );
                    continue;
                }

                // Fallback: rate-based points for this item's price
                if ($rate) {
                    $totalPoints += $this->earningCalculator->calculateFromRate($itemPrice, $rate, $storeId);
                }
            }

            // Add earning on tax and/or shipping amounts when configured.
            // Cart rules do not add tax/shipping points; only rate-based earning does.
            if ($rate && empty($matchingCartRules)) {
                $extraBase = 0.0;

                if ($this->config->isEarnFromTax($storeId)) {
                    $extraBase += (float) $order->getBaseTaxAmount();
                }

                if ($this->config->isEarnFromShipping($storeId)) {
                    $extraBase += (float) $order->getBaseShippingAmount();
                }

                if ($extraBase > 0) {
                    $totalPoints += $this->earningCalculator->calculateFromRate($extraBase, $rate, $storeId);
                }
            }

            $maxBalance = $this->config->getMaxBalance($websiteId);

            if ($maxBalance > 0 && $totalPoints > $maxBalance) {
                $totalPoints = $maxBalance;
            }

            return max(0, $totalPoints);
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error calculating order earning points',
                [
                    'order_id' => $order->getEntityId(),
                    'exception' => $e,
                ],
            );

            return 0;
        }
    }

    /**
     * Calculate points for one order item using the first matching cart rule.
     *
     * Rules are already sorted by priority ASC and stop_rules_processing has been
     * applied during getMatchingRulesForOrder() — so this method just processes
     * all rules in the pre-filtered list and sums their contributions, respecting
     * per-rule stop_rules_processing for subsequent items as well.
     *
     * @param CartRule[] $matchingCartRules  Pre-filtered, priority-sorted list
     * @param float $itemPrice              Base row total after discount
     * @param float $itemQty
     * @return int
     */
    private function calculateCartRulePointsForItem(array $matchingCartRules, float $itemPrice, float $itemQty): int
    {
        $unitPrice = $itemQty > 0 ? ($itemPrice / $itemQty) : $itemPrice;
        $totalPoints = 0;

        foreach ($matchingCartRules as $rule) {
            $moneyStep = (float) $rule->getMoneyStep();
            $points    = $rule->getPoints();
            $maxPoints = $rule->getMaxPoints();

            // Skip per_price/per_qty rules that have an invalid (NULL or zero) money_step.
            // Such rules should have been blocked by the admin form validation; treating them
            // as money_step=1 would massively over-award points (e.g. $98 → 98 steps × 15 pts).
            if (in_array($rule->getActionType(), ['per_price', 'per_qty'], true) && $moneyStep <= 0) {
                $this->logger->warning(
                    'RewardPoints: cart rule skipped — money_step is NULL or zero for per_price/per_qty rule',
                    ['rule_id' => $rule->getId(), 'action_type' => $rule->getActionType()],
                );

                if ($rule->isStopRulesProcessing()) {
                    break;
                }

                continue;
            }

            $earned = match ($rule->getActionType()) {
                'fixed'     => (int) ($points * $itemQty),
                'per_price' => ($moneyStep > 0)
                    ? (int) (floor($unitPrice / $moneyStep) * $points * $itemQty)
                    : 0,
                default     => (int) ($points * $itemQty),
            };

            if ($maxPoints > 0 && $earned > $maxPoints) {
                $earned = $maxPoints;
            }

            $totalPoints += max(0, $earned);

            if ($rule->isStopRulesProcessing()) {
                break;
            }
        }

        return $totalPoints;
    }

    /**
     * Evaluate catalog rule index rows for a single order item.
     *
     * Rules are ordered by priority ASC.  Processing stops when stop_rules_processing = 1.
     * Returns null when no catalog rule applies (caller should use rate-based fallback).
     *
     * @param int $productId
     * @param int $websiteId
     * @param int $customerGroupId
     * @param float $itemPrice  Row total after discount (base currency)
     * @param float $itemQty
     * @return int|null
     */
    private function getCatalogRulePointsForItem(
        int $productId,
        int $websiteId,
        int $customerGroupId,
        float $itemPrice,
        float $itemQty,
    ): ?int {
        $connection = $this->resourceConnection->getConnection();
        $indexTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_catalog_rule_product');

        $select = $connection->select()
            ->from($indexTable, ['rule_id', 'points', 'action_type', 'money_step', 'max_points', 'stop_rules_processing'])
            ->where('product_id = ?', $productId)
            ->where('website_id = ?', $websiteId)
            ->where('customer_group_id = ?', $customerGroupId)
            ->order('priority ASC');

        $rows = $connection->fetchAll($select);

        if (empty($rows)) {
            return null;
        }

        $unitPrice  = $itemQty > 0 ? ($itemPrice / $itemQty) : $itemPrice;
        $totalPoints = 0;

        foreach ($rows as $row) {
            $moneyStep = (float) $row['money_step'];

            // Skip per_price rules with an invalid money_step rather than silently defaulting to 1.
            if (in_array($row['action_type'], ['per_price', 'per_qty'], true) && $moneyStep <= 0) {
                $this->logger->warning(
                    'RewardPoints: catalog rule skipped — money_step is NULL or zero',
                    ['rule_id' => $row['rule_id'], 'action_type' => $row['action_type']],
                );

                if (!empty($row['stop_rules_processing'])) {
                    break;
                }

                continue;
            }

            $rulePoints = $this->calculateCatalogRulePoints(
                (string) $row['action_type'],
                (int) $row['points'],
                max(0.0001, $moneyStep),
                (int) $row['max_points'],
                $unitPrice,
                $itemQty,
            );
            $totalPoints += $rulePoints;

            if (!empty($row['stop_rules_processing'])) {
                break;
            }
        }

        return $totalPoints;
    }

    /**
     * Compute points for one catalog rule match.
     *
     * @param string $actionType  'fixed' | 'per_price'
     * @param int $points
     * @param float $moneyStep
     * @param int $maxPoints     0 = unlimited
     * @param float $unitPrice   Per-unit price (base currency)
     * @param float $qty
     * @return int
     */
    private function calculateCatalogRulePoints(
        string $actionType,
        int $points,
        float $moneyStep,
        int $maxPoints,
        float $unitPrice,
        float $qty,
    ): int {
        $earned = match ($actionType) {
            'fixed'     => (int) ($points * $qty),
            'per_price' => ($moneyStep > 0)
                ? (int) (floor($unitPrice / $moneyStep) * $points * $qty)
                : 0,
            default     => (int) ($points * $qty),
        };

        if ($maxPoints > 0 && $earned > $maxPoints) {
            $earned = $maxPoints;
        }

        return max(0, $earned);
    }

    /**
     * Get the highest-priority active earning rate for a given website and customer group.
     *
     * A rate is eligible when:
     *  - It has no website restriction (no row in junction table), OR its rate_id appears in
     *    the website junction table for the given websiteId with rule_type = 'earning_rate'
     *  - It has no customer-group restriction (no row in junction table), OR its rate_id
     *    appears in the customer-group junction table for the given customerGroupId
     *
     * @param int $websiteId
     * @param int $customerGroupId
     * @return EarningRateInterface|null
     */
    private function getApplicableEarningRate(int $websiteId, int $customerGroupId): ?EarningRateInterface
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(EarningRateInterface::IS_ACTIVE, 1)
                ->create();

            $results = $this->earningRateRepository->getList($searchCriteria);
            $rates = $results->getItems();

            if (empty($rates)) {
                return null;
            }

            $eligibleRateIds = $this->getEligibleRateIds(
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
            $this->logger->error('RewardPoints: getApplicableEarningRate error', ['exception' => $e]);

            return null;
        }
    }

    /**
     * Filter rate IDs by website and customer-group junction tables.
     *
     * A rate passes if:
     *  - No rows exist in the website junction for this rate_type → applies to all websites
     *  - OR the websiteId is listed for this rate
     * Same logic for customer group.
     *
     * @param array<int|string> $allRateIds
     * @param int $websiteId
     * @param int $customerGroupId
     * @return array<int|string>
     */
    private function getEligibleRateIds(array $allRateIds, int $websiteId, int $customerGroupId): array
    {
        if (empty($allRateIds)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $ruleType = 'earning_rate';

        // Rates that have AT LEAST ONE website restriction row
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $restrictedWebsiteSelect = $connection->select()
            ->from($websiteTable, ['rule_id'])
            ->where('rule_type = ?', $ruleType)
            ->where('rule_id IN (?)', $allRateIds);
        $restrictedWebsiteIds = $connection->fetchCol($restrictedWebsiteSelect);

        // Of those restricted rates, which ones allow this website?
        $allowedWebsiteSelect = $connection->select()
            ->from($websiteTable, ['rule_id'])
            ->where('rule_type = ?', $ruleType)
            ->where('website_id = ?', $websiteId)
            ->where('rule_id IN (?)', $allRateIds);
        $allowedByWebsite = $connection->fetchCol($allowedWebsiteSelect);

        // Rates that have AT LEAST ONE customer-group restriction row
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');
        $restrictedCgSelect = $connection->select()
            ->from($cgTable, ['rule_id'])
            ->where('rule_type = ?', $ruleType)
            ->where('rule_id IN (?)', $allRateIds);
        $restrictedCgIds = $connection->fetchCol($restrictedCgSelect);

        // Of those restricted rates, which ones allow this customer group?
        $allowedCgSelect = $connection->select()
            ->from($cgTable, ['rule_id'])
            ->where('rule_type = ?', $ruleType)
            ->where('customer_group_id = ?', $customerGroupId)
            ->where('rule_id IN (?)', $allRateIds);
        $allowedByGroup = $connection->fetchCol($allowedCgSelect);

        $eligible = [];

        foreach ($allRateIds as $rateId) {
            $websiteOk = !in_array($rateId, $restrictedWebsiteIds)
                || in_array($rateId, $allowedByWebsite);
            $groupOk = !in_array($rateId, $restrictedCgIds)
                || in_array($rateId, $allowedByGroup);

            if ($websiteOk && $groupOk) {
                $eligible[] = $rateId;
            }
        }

        return $eligible;
    }
}

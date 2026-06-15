<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Product;

use Magento\Catalog\Model\Product;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\CollectionFactory as CartRuleCollectionFactory;

/**
 * Block that renders "Earn X points on this purchase" on the product detail page.
 *
 * Points are estimated from:
 *  1. The best active catalog earning rule matching this product (via index table).
 *  2. Fallback: the highest-priority active earning rate (X pts per $Y).
 */
class EarnMessage extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param CartRuleCollectionFactory $cartRuleCollectionFactory
     * @param CustomerSession $customerSession
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly CartRuleCollectionFactory $cartRuleCollectionFactory,
        private readonly CustomerSession $customerSession,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the current product from the registry
     *
     * @return Product|null
     */
    public function getCurrentProduct(): ?Product
    {
        return $this->registry->registry('current_product');
    }

    /**
     * Check whether the earn message should be shown
     *
     * @return bool
     */
    public function canShow(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }

        if (!$this->config->isShowOnProduct()) {
            return false;
        }

        return $this->getCurrentProduct() !== null;
    }

    /**
     * Estimate how many points a customer would earn for the current product.
     *
     * Returns null if no applicable rule/rate found.
     *
     * @return int|null
     */
    public function getEstimatedPoints(): ?int
    {
        $product = $this->getCurrentProduct();

        if (!$product) {
            return null;
        }

        $price     = (float) $product->getFinalPrice();
        $websiteId = (int) $this->storeManager->getWebsite()->getId();
        $productId = (int) $product->getId();

        // 1. Try catalog rule index
        $points = $this->getPointsFromCatalogRuleIndex($productId, $websiteId, $price);

        if ($points !== null) {
            return $points;
        }

        // 2. Fallback: earning rate
        return $this->getPointsFromEarningRate($price, $websiteId);
    }

    /**
     * Get formatted point label
     *
     * @param int $points
     * @return string
     */
    public function formatPoints(int $points): string
    {
        return $this->config->formatPoints($points);
    }

    /**
     * Get active cart earning rules that have "Show on Product Page" enabled.
     *
     * These are order-level incentive messages shown below the catalog earn message.
     * Filtered by:
     *  - is_active = 1
     *  - is_shown_on_product_page = 1
     *  - website matches current website
     *  - customer group matches current customer (logged in or guest)
     *  - date range is valid (or not set)
     *
     * @return \Meetanshi\RewardPoints\Model\Rule\CartRule[]
     */
    public function getCartRulesForProductPage(): array
    {
        $websiteId       = (int) $this->storeManager->getWebsite()->getId();
        $customerGroupId = (int) $this->customerSession->getCustomerGroupId();
        $today           = date('Y-m-d');

        $connection  = $this->resourceConnection->getConnection();
        $ruleTable   = $this->resourceConnection->getTableName('meetanshi_rewardpoints_cart_rule');
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $groupTable  = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        // Get rule IDs that apply to this website
        $websiteRuleIds = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['rule_id'])
                ->where('rule_type = ?', 'cart_earning')
                ->where('website_id = ?', $websiteId),
        );

        // Get rule IDs that apply to this customer group
        $groupRuleIds = $connection->fetchCol(
            $connection->select()
                ->from($groupTable, ['rule_id'])
                ->where('rule_type = ?', 'cart_earning')
                ->where('customer_group_id = ?', $customerGroupId),
        );

        $collection = $this->cartRuleCollectionFactory->create();
        $collection->addFieldToFilter('is_active', 1);
        $collection->addFieldToFilter('is_shown_on_product_page', 1);
        $collection->addFieldToFilter('rule_id', ['in' => $websiteRuleIds]);
        $collection->addFieldToFilter('rule_id', ['in' => $groupRuleIds]);

        // Date range filter
        $collection->addFieldToFilter(
            ['from_date', 'from_date'],
            [['null' => true], ['lteq' => $today]],
        );
        $collection->addFieldToFilter(
            ['to_date', 'to_date'],
            [['null' => true], ['gteq' => $today]],
        );

        $collection->setOrder('priority', 'ASC');

        return $collection->getItems();
    }

    /**
     * Build a human-readable promotional label for a cart rule.
     *
     * Examples:
     *  - fixed:     "Earn 10 points on this order"
     *  - per_price: "Earn 15 points per £10 spent"
     *  - per_qty:   "Earn 2 points per item"
     *
     * @param \Meetanshi\RewardPoints\Model\Rule\CartRule $rule
     * @return string
     */
    public function getCartRuleLabel(\Meetanshi\RewardPoints\Model\Rule\CartRule $rule): string
    {
        $points    = (int) $rule->getPoints();
        $formatted = $this->config->formatPoints($points);

        return match ($rule->getActionType()) {
            'per_price' => (string) __(
                'Earn %1 for every %2 spent',
                $formatted,
                $this->formatCurrency((float) $rule->getMoneyStep()),
            ),
            'per_qty'   => (string) __('Earn %1 per item', $formatted),
            default     => (string) __('Earn %1 on this order', $formatted),
        };
    }

    /**
     * Format a currency amount using the base currency symbol
     *
     * @param float $amount
     * @return string
     */
    private function formatCurrency(float $amount): string
    {
        try {
            $store = $this->storeManager->getStore();
            return $store->getBaseCurrency()->formatTxt($amount, ['display' => \Magento\Framework\Currency::NO_SYMBOL])
                ? $store->getBaseCurrency()->getCurrencySymbol() . number_format($amount, 2)
                : number_format($amount, 2);
        } catch (\Exception $e) {
            return number_format($amount, 2);
        }
    }

    /**
     * Look up points from the catalog rule product index.
     *
     * @param int $productId
     * @param int $websiteId
     * @param float $price
     * @return int|null
     */
    private function getPointsFromCatalogRuleIndex(int $productId, int $websiteId, float $price): ?int
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('meetanshi_rewardpoints_catalog_rule_product');

        $select = $connection->select()
            ->from($table, ['points', 'action_type', 'money_step', 'max_points'])
            ->where('product_id = ?', $productId)
            ->where('website_id = ?', $websiteId)
            ->order('priority ASC')
            ->limit(1);

        $row = $connection->fetchRow($select);

        if (!$row) {
            return null;
        }

        return $this->calculatePoints(
            (string) $row['action_type'],
            (int) $row['points'],
            (float) ($row['money_step'] ?: 1),
            (int) $row['max_points'],
            $price,
        );
    }

    /**
     * Calculate points from the best-matched active earning rate.
     *
     * Picks the rate with the highest-priority (lowest priority number)
     * that applies to the current website.
     *
     * @param float $price
     * @param int $websiteId
     * @return int|null
     */
    private function getPointsFromEarningRate(float $price, int $websiteId): ?int
    {
        $connection    = $this->resourceConnection->getConnection();
        $rateTable     = $this->resourceConnection->getTableName('meetanshi_rewardpoints_earning_rate');
        $websiteTable  = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');

        $select = $connection->select()
            ->from(['r' => $rateTable], ['points', 'money_step', 'min_order_total'])
            ->joinInner(
                ['w' => $websiteTable],
                "w.rule_id = r.rate_id AND w.rule_type = 'earning_rate' AND w.website_id = {$websiteId}",
                [],
            )
            ->where('r.is_active = ?', 1)
            ->where('r.min_order_total IS NULL OR r.min_order_total <= ?', $price)
            ->order('r.priority ASC')
            ->limit(1);

        $row = $connection->fetchRow($select);

        if (!$row) {
            return null;
        }

        $moneyStep = (float) ($row['money_step'] ?: 1);
        $points    = (int) $row['points'];

        if ($moneyStep <= 0 || $price <= 0) {
            return null;
        }

        $earned = (int) floor(($price / $moneyStep) * $points);

        return $earned > 0 ? $earned : null;
    }

    /**
     * Check if social sharing is enabled on product pages.
     *
     * @return bool
     */
    public function isSocialEnabledOnProduct(): bool
    {
        return in_array('product', $this->config->getSocialPages(), true);
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

    /**
     * Calculate points from action type, points, step, max, and price.
     *
     * @param string $actionType
     * @param int $points
     * @param float $moneyStep
     * @param int $maxPoints
     * @param float $price
     * @return int
     */
    private function calculatePoints(
        string $actionType,
        int $points,
        float $moneyStep,
        int $maxPoints,
        float $price,
    ): int {
        $earned = match ($actionType) {
            'fixed'     => $points,
            'per_price' => ($moneyStep > 0) ? (int) floor(($price / $moneyStep) * $points) : 0,
            default     => $points,
        };

        if ($maxPoints > 0 && $earned > $maxPoints) {
            $earned = $maxPoints;
        }

        return max(0, $earned);
    }
}

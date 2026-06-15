<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Catalog;

use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Block for "Earn X points" message on category listing / product list pages.
 *
 * Called from within the product list item template with the product passed in.
 */
class EarnMessageListing extends Template
{
    /**
     * @param Context $context
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Check whether the earn message should be shown on category/listing pages
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->config->isShowOnCategory();
    }

    /**
     * Estimate points for a given product, based on earning rate.
     *
     * @param Product $product
     * @return int|null
     */
    public function getEstimatedPoints(Product $product): ?int
    {
        $price     = (float) $product->getFinalPrice();
        $websiteId = (int) $this->storeManager->getWebsite()->getId();

        if ($price <= 0) {
            return null;
        }

        // Try catalog rule index first
        $points = $this->getPointsFromCatalogRuleIndex((int) $product->getId(), $websiteId, $price);

        if ($points !== null) {
            return $points;
        }

        // Fallback to earning rate
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

        $actionType = (string) $row['action_type'];
        $pts        = (int) $row['points'];
        $moneyStep  = (float) ($row['money_step'] ?: 1);
        $maxPoints  = (int) $row['max_points'];

        $earned = match ($actionType) {
            'fixed'     => $pts,
            'per_price' => ($moneyStep > 0) ? (int) floor(($price / $moneyStep) * $pts) : 0,
            default     => $pts,
        };

        if ($maxPoints > 0 && $earned > $maxPoints) {
            $earned = $maxPoints;
        }

        return $earned > 0 ? $earned : null;
    }

    /**
     * Calculate points from the best-matched active earning rate.
     *
     * @param float $price
     * @param int $websiteId
     * @return int|null
     */
    private function getPointsFromEarningRate(float $price, int $websiteId): ?int
    {
        $connection   = $this->resourceConnection->getConnection();
        $rateTable    = $this->resourceConnection->getTableName('meetanshi_rewardpoints_earning_rate');
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');

        $select = $connection->select()
            ->from(['r' => $rateTable], ['points', 'money_step'])
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

        if ($moneyStep <= 0) {
            return null;
        }

        $earned = (int) floor(($price / $moneyStep) * $points);

        return $earned > 0 ? $earned : null;
    }
}

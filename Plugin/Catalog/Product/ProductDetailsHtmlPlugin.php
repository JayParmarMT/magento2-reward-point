<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Catalog\Product;

use Magento\Catalog\Block\Product\AbstractProduct;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Escaper;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Appends "Earn X points" message to each product card on category/listing pages.
 *
 * Uses an after-plugin on AbstractProduct::getProductDetailsHtml() so the message
 * is appended to every product card regardless of which renderer is used.
 * This is necessary because category.product.type.details.renderers.default is a
 * plain Template block with no template, so its children are never rendered.
 */
class ProductDetailsHtmlPlugin
{
    /**
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param Escaper $escaper
     */
    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper,
    ) {
    }

    /**
     * Append earn points message after the product details HTML on listing pages.
     *
     * @param AbstractProduct $subject
     * @param string $result
     * @param Product $product
     * @return string
     */
    public function afterGetProductDetailsHtml(
        AbstractProduct $subject,
        string $result,
        Product $product,
    ): string {
        if (!$this->config->isEnabled() || !$this->config->isShowOnCategory()) {
            return $result;
        }

        $points = $this->getEstimatedPoints($product);

        if (!$points) {
            return $result;
        }

        $label = $this->escaper->escapeHtml(
            (string) __('Earn %1', $this->config->formatPoints($points)),
        );

        $html = <<<HTML
<div class="reward-points-earn-message reward-points-earn-item reward-points-earn-catalog">
    <span class="reward-points-earn-icon">🎁</span>
    <span class="reward-points-earn-text">{$label}</span>
</div>
HTML;

        return $result . $html;
    }

    /**
     * Estimate points for the given product.
     *
     * Priority: catalog rule index → earning rate fallback.
     *
     * @param Product $product
     * @return int|null
     */
    private function getEstimatedPoints(Product $product): ?int
    {
        $price     = (float) $product->getFinalPrice();
        $websiteId = (int) $this->storeManager->getWebsite()->getId();

        if ($price <= 0) {
            return null;
        }

        $points = $this->getPointsFromCatalogRuleIndex((int) $product->getId(), $websiteId, $price);

        if ($points !== null) {
            return $points;
        }

        return $this->getPointsFromEarningRate($price, $websiteId);
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

        $row = $connection->fetchRow(
            $connection->select()
                ->from($table, ['points', 'action_type', 'money_step', 'max_points'])
                ->where('product_id = ?', $productId)
                ->where('website_id = ?', $websiteId)
                ->order('priority ASC')
                ->limit(1),
        );

        if (!$row) {
            return null;
        }

        $pts       = (int) $row['points'];
        $moneyStep = (float) ($row['money_step'] ?: 1);
        $maxPoints = (int) $row['max_points'];

        $earned = match ((string) $row['action_type']) {
            'fixed'     => $pts,
            'per_price' => ($moneyStep > 0) ? (int) (floor($price / $moneyStep) * $pts) : 0,
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

        $row = $connection->fetchRow(
            $connection->select()
                ->from(['r' => $rateTable], ['points', 'money_step'])
                ->joinInner(
                    ['w' => $websiteTable],
                    "w.rule_id = r.rate_id AND w.rule_type = 'earning_rate' AND w.website_id = {$websiteId}",
                    [],
                )
                ->where('r.is_active = ?', 1)
                ->where('r.min_order_total IS NULL OR r.min_order_total <= ?', $price)
                ->order('r.priority ASC')
                ->limit(1),
        );

        if (!$row) {
            return null;
        }

        $moneyStep = (float) ($row['money_step'] ?: 1);
        $points    = (int) $row['points'];

        if ($moneyStep <= 0 || $price <= 0) {
            return null;
        }

        $earned = (int) (floor($price / $moneyStep) * $points);

        return $earned > 0 ? $earned : null;
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Sales\Order\Totals;

use Magento\Framework\DataObject;
use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Adds the Reward Points discount row to the frontend order/invoice/creditmemo totals block.
 *
 * Works across:
 *   sales_order_view     — source IS the order
 *   sales_order_invoice  — source is invoice  → walk to getOrder()
 *   sales_order_creditmemo — source is creditmemo → walk to getOrder()
 *
 * Falls back to core registry when the block graph hasn't been fully wired.
 */
class RewardPoints extends Template
{
    /**
     * @param Context $context
     * @param Config $config
     * @param Registry $registry
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly Registry $registry,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Add reward points discount row to the parent totals block.
     *
     * The grand total already includes the reward discount (applied at order
     * placement), so we only need to inject the display row — no adjustment
     * to the grand_total DataObject is required.
     *
     * @return $this
     */
    public function initTotals(): static
    {
        $order = $this->resolveOrder();

        if (!$order) {
            return $this;
        }

        $pointsUsed         = (int)   $order->getData('reward_points_used');
        $discountAmount     = (float) $order->getData('reward_points_discount');
        $baseDiscountAmount = (float) $order->getData('base_reward_points_discount');

        // Fall back to display amount if base column not yet populated (e.g. orders
        // placed before the multi-currency upgrade) so existing orders still display.
        if ($baseDiscountAmount <= 0.0) {
            $baseDiscountAmount = $discountAmount;
        }

        if ($pointsUsed <= 0 || $discountAmount <= 0.0) {
            return $this;
        }

        $label = $this->config->getDiscountLabel() ?: (string) __('Reward Points');

        $this->getParentBlock()->addTotalBefore(
            new DataObject([
                'code'       => 'reward_points',
                'label'      => __('%1 (%2 pts)', $label, $pointsUsed),
                'value'      => -$discountAmount,
                'base_value' => -$baseDiscountAmount,
            ]),
            'grand_total',
        );

        return $this;
    }

    /**
     * Resolve the order object from the parent totals block source or registry.
     *
     * @return \Magento\Sales\Model\Order|null
     */
    private function resolveOrder(): ?\Magento\Sales\Model\Order
    {
        $parent = $this->getParentBlock();

        if ($parent && method_exists($parent, 'getSource')) {
            $source = $parent->getSource();

            if ($source) {
                if ($source instanceof \Magento\Sales\Model\Order) {
                    return $source;
                }

                if (method_exists($source, 'getOrder')) {
                    $order = $source->getOrder();

                    if ($order instanceof \Magento\Sales\Model\Order) {
                        return $order;
                    }
                }
            }
        }

        // Fallback: registry
        foreach (['current_order', 'current_invoice', 'current_creditmemo'] as $key) {
            $obj = $this->registry->registry($key);

            if (!$obj) {
                continue;
            }

            if ($obj instanceof \Magento\Sales\Model\Order) {
                return $obj;
            }

            if (method_exists($obj, 'getOrder')) {
                $order = $obj->getOrder();

                if ($order instanceof \Magento\Sales\Model\Order) {
                    return $order;
                }
            }
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Order\Creditmemo\Total;

use Magento\Sales\Model\Order\Creditmemo;
use Magento\Sales\Model\Order\Creditmemo\Total\AbstractTotal;

/**
 * Creditmemo total collector — deducts the reward points discount from the creditmemo grand total.
 *
 * Mirrors Meetanshi\RewardPoints\Model\Order\Invoice\Total\RewardPoints but for
 * the creditmemo pipeline.  Applied only on the first creditmemo for an order to
 * avoid double-deduction on partial refunds.
 *
 * Multi-currency: reward_points_discount is order/display currency;
 * base_reward_points_discount is base currency.  Both are applied to their
 * respective grand total columns.
 */
class RewardPoints extends AbstractTotal
{
    /**
     * Collect reward-points discount into the creditmemo grand total.
     *
     * @param Creditmemo $creditmemo
     * @return $this
     */
    public function collect(Creditmemo $creditmemo): static
    {
        $order = $creditmemo->getOrder();

        $discountAmount     = (float) $order->getData('reward_points_discount');
        $baseDiscountAmount = (float) $order->getData('base_reward_points_discount');
        $pointsUsed         = (int)   $order->getData('reward_points_used');

        if ($pointsUsed <= 0 || $discountAmount <= 0.0) {
            return $this;
        }

        // Only apply the reward discount once across all creditmemos for this order.
        foreach ($order->getCreditmemosCollection() as $previousCreditmemo) {
            if ((float) $previousCreditmemo->getData('rp_reward_points_discount') > 0) {
                return $this;
            }
        }

        // Persist to the creditmemo's own columns (rp_ prefix avoids conflict with core columns).
        $creditmemo->setData('rp_reward_points_used', $pointsUsed);
        $creditmemo->setData('rp_reward_points_discount', $discountAmount);
        $creditmemo->setData('rp_base_reward_points_discount', $baseDiscountAmount);

        // Reduce grand totals — use the correct currency for each column.
        $creditmemo->setGrandTotal(
            max(0.0, $creditmemo->getGrandTotal() - $discountAmount),
        );
        $creditmemo->setBaseGrandTotal(
            max(0.0, $creditmemo->getBaseGrandTotal() - $baseDiscountAmount),
        );

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Order\Invoice\Total;

use Magento\Sales\Model\Order\Invoice;
use Magento\Sales\Model\Order\Invoice\Total\AbstractTotal;

/**
 * Invoice total collector — deducts the reward points discount from the invoice grand total.
 *
 * Magento's invoice collectTotals() pipeline does not know about reward points,
 * so without this collector the grand_total shown on the invoice creation page is
 * inflated by the reward discount amount.  This collector mirrors the pattern used
 * by Magento\Sales\Model\Order\Invoice\Total\Discount.
 *
 * Only the first invoice for an order receives the full reward deduction; if a
 * previous invoice already carried it we skip to avoid double-deducting on partial
 * invoices.
 *
 * Multi-currency: reward_points_discount is the order/display-currency amount;
 * base_reward_points_discount is the base-currency amount.  Both are applied to
 * their respective grand total columns so that Magento's financial reports remain
 * accurate regardless of the order currency.
 */
class RewardPoints extends AbstractTotal
{
    /**
     * Collect reward-points discount into the invoice grand total.
     *
     * @param Invoice $invoice
     * @return $this
     */
    public function collect(Invoice $invoice): static
    {
        $order = $invoice->getOrder();

        $discountAmount     = (float) $order->getData('reward_points_discount');
        $baseDiscountAmount = (float) $order->getData('base_reward_points_discount');
        $pointsUsed         = (int)   $order->getData('reward_points_used');

        if ($pointsUsed <= 0 || $discountAmount <= 0.0) {
            return $this;
        }

        // Only apply the reward discount once across all invoices for this order.
        // If a previous invoice already carried a reward deduction, skip.
        foreach ($order->getInvoiceCollection() as $previousInvoice) {
            if ((float) $previousInvoice->getData('rp_reward_points_discount') > 0) {
                return $this;
            }
        }

        // Persist to the invoice's own columns (rp_ prefix avoids conflict with core columns).
        $invoice->setData('rp_reward_points_used', $pointsUsed);
        $invoice->setData('rp_reward_points_discount', $discountAmount);
        $invoice->setData('rp_base_reward_points_discount', $baseDiscountAmount);

        // Reduce grand totals — use the correct currency for each column.
        $invoice->setGrandTotal(
            max(0.0, $invoice->getGrandTotal() - $discountAmount),
        );
        $invoice->setBaseGrandTotal(
            max(0.0, $invoice->getBaseGrandTotal() - $baseDiscountAmount),
        );

        return $this;
    }
}

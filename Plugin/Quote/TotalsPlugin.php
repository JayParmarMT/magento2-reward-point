<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Quote;

use Magento\Quote\Api\Data\ShippingAssignmentInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\Total;
use Magento\Quote\Model\Quote\Address\Total\AbstractTotal;
use Meetanshi\RewardPoints\Model\Quote\Item\SellByPoints;

/**
 * Plugin on quote total collection to handle sell-by-points items
 */
class TotalsPlugin
{
    private const QUOTE_FLAG_HAS_SELL_BY_POINTS = 'meetanshi_rp_has_sell_by_points';

    /**
     * @param SellByPoints $sellByPoints
     */
    public function __construct(
        private readonly SellByPoints $sellByPoints,
    ) {
    }

    /**
     * After collect totals: zero out sell-by-points item row totals and set flag
     *
     * @param AbstractTotal $subject
     * @param AbstractTotal $result
     * @param Quote $quote
     * @param ShippingAssignmentInterface $shippingAssignment
     * @param Total $total
     * @return AbstractTotal
     */
    public function afterCollect(
        AbstractTotal $subject,
        AbstractTotal $result,
        Quote $quote,
        ShippingAssignmentInterface $shippingAssignment,
        Total $total,
    ): AbstractTotal {
        $hasSellByPointsItems = false;

        foreach ($shippingAssignment->getItems() as $item) {
            if ($this->sellByPoints->isSellByPointsItem($item)) {
                $hasSellByPointsItems = true;
                // Zero out the row total for sell-by-points items
                $item->setRowTotal(0);
                $item->setRowTotalInclTax(0);
                $item->setBaseRowTotal(0);
                $item->setBaseRowTotalInclTax(0);
            }
        }

        if ($hasSellByPointsItems) {
            $quote->setData(self::QUOTE_FLAG_HAS_SELL_BY_POINTS, true);
        }

        return $result;
    }
}

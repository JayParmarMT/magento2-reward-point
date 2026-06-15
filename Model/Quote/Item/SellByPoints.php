<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Quote\Item;

use Magento\Quote\Model\Quote\Item;

/**
 * Sell By Points — Quote Item helper service
 */
class SellByPoints
{
    private const QUOTE_ITEM_FLAG = 'meetanshi_rp_buy_with_points';

    /**
     * Check if a quote item was added via "buy with points" flow
     *
     * @param Item $item
     * @return bool
     */
    public function isSellByPointsItem(Item $item): bool
    {
        if (!$this->hasPointPrice($item)) {
            return false;
        }

        return (bool) $item->getData(self::QUOTE_ITEM_FLAG);
    }

    /**
     * Get the point price for a quote item's product
     *
     * @param Item $item
     * @return int
     */
    public function getItemPointPrice(Item $item): int
    {
        $product = $item->getProduct();

        if (!$product) {
            return 0;
        }

        return (int) ($product->getData('meetanshi_rp_point_price') ?? 0);
    }

    /**
     * Check if item product has a point price set
     *
     * @param Item $item
     * @return bool
     */
    private function hasPointPrice(Item $item): bool
    {
        return $this->getItemPointPrice($item) > 0;
    }
}

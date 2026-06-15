<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Copies reward points data from quote to order before the order is saved.
 *
 * This runs during sales_model_service_quote_submit_before, which fires
 * before orderManagement::place() persists the order to the database.
 * This ensures reward_points_used, reward_points_discount, and
 * base_reward_points_discount are written to the sales_order table
 * in the initial INSERT rather than requiring a secondary UPDATE.
 */
class QuoteSubmitBeforeObserver implements ObserverInterface
{
    /**
     * Copy reward points fields from quote to order
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();

        /** @var CartInterface $quote */
        $quote = $observer->getEvent()->getQuote();

        if (!$order || !$quote) {
            return;
        }

        $pointsUsed         = (int)   $quote->getData('reward_points_used');
        $discountAmount     = (float) $quote->getData('reward_points_discount');
        $baseDiscountAmount = (float) $quote->getData('base_reward_points_discount');

        if ($pointsUsed <= 0) {
            return;
        }

        $order->setData('reward_points_used', $pointsUsed);
        $order->setData('reward_points_discount', $discountAmount);
        $order->setData('base_reward_points_discount', $baseDiscountAmount);
    }
}

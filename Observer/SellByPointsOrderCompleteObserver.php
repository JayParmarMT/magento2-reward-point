<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Exception\InsufficientBalanceException;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Observer for sell-by-points order completion — deducts points for "buy with points" items
 */
class SellByPointsOrderCompleteObserver implements ObserverInterface
{
    private const ORDER_FLAG_PROCESSED = 'meetanshi_rp_sell_by_points_processed';

    /**
     * @param BalanceManagementInterface $balanceManagement
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var OrderInterface|Order $order */
        $order = $observer->getData('order');

        if (!$order || !$order->getEntityId()) {
            return;
        }

        if ($order->getStatus() !== Order::STATE_COMPLETE) {
            return;
        }

        $customerId = (int) $order->getCustomerId();

        if ($customerId <= 0) {
            return;
        }

        // Idempotency check — do not process twice
        if ($order->getData(self::ORDER_FLAG_PROCESSED)) {
            return;
        }

        try {
            $totalPointsToDeduct = $this->calculateTotalPointsToDeduct($order);

            if ($totalPointsToDeduct <= 0) {
                return;
            }

            $websiteId = (int) $order->getStore()->getWebsiteId();
            $orderId = (int) $order->getEntityId();
            $storeId = (int) $order->getStoreId();

            $this->balanceManagement->subtractPoints(
                $customerId,
                $websiteId,
                $totalPointsToDeduct,
                'spend_order',
                (string) __('Deducted for sell-by-points items in order #%1', $order->getIncrementId()),
                [
                    'order_id' => $orderId,
                    'store_id' => $storeId,
                ],
            );
        } catch (InsufficientBalanceException $e) {
            $this->logger->warning(
                'RewardPoints: SellByPointsOrderCompleteObserver - insufficient balance',
                [
                    'order_id' => $order->getEntityId(),
                    'customer_id' => $customerId,
                    'message' => $e->getMessage(),
                ],
            );
        } catch (LocalizedException $e) {
            $this->logger->warning(
                'RewardPoints: SellByPointsOrderCompleteObserver failed (LocalizedException)',
                [
                    'order_id' => $order->getEntityId(),
                    'message' => $e->getMessage(),
                ],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: SellByPointsOrderCompleteObserver unexpected error',
                [
                    'order_id' => $order->getEntityId(),
                    'exception' => $e,
                ],
            );
        }
    }

    /**
     * Calculate total points to deduct from sell-by-points items in order
     *
     * @param Order $order
     * @return int
     */
    private function calculateTotalPointsToDeduct(Order $order): int
    {
        $totalPoints = 0;

        foreach ($order->getAllVisibleItems() as $item) {
            $pointPrice = (int) ($item->getProductOptionByCode('meetanshi_rp_point_price') ?? 0);

            if ($pointPrice <= 0) {
                // Also check product attribute directly
                $product = $item->getProduct();

                if ($product) {
                    $pointPrice = (int) ($product->getData('meetanshi_rp_point_price') ?? 0);
                }
            }

            if ($pointPrice > 0 && $item->getData('meetanshi_rp_buy_with_points')) {
                $qty = (int) $item->getQtyOrdered();
                $totalPoints += $pointPrice * $qty;
            }
        }

        return $totalPoints;
    }
}

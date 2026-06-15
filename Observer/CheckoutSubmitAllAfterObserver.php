<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Api\Data\CartInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Creates spend_order transaction after successful order placement
 */
class CheckoutSubmitAllAfterObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param BalanceManagementInterface $balanceManagement
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute observer: deduct reward points after order is placed
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $observer->getEvent()->getOrder();

        /** @var CartInterface $quote */
        $quote = $observer->getEvent()->getQuote();

        if (!$order || !$quote) {
            return;
        }

        $pointsUsed = (int) $order->getData('reward_points_used');

        if ($pointsUsed <= 0) {
            return;
        }

        $customerId = (int) $order->getCustomerId();
        $orderId = (int) $order->getEntityId();

        if (!$customerId || !$orderId) {
            return;
        }

        if ($this->hasExistingSpendTransaction($orderId, $customerId)) {
            return;
        }

        try {
            $websiteId = (int) $this->storeManager->getStore((int) $order->getStoreId())->getWebsiteId();

            $this->balanceManagement->subtractPoints(
                $customerId,
                $websiteId,
                $pointsUsed,
                TransactionInterface::ACTION_SPEND_ORDER,
                (string) __('Points used on order #%1', $order->getIncrementId()),
                [
                    'order_id' => $orderId,
                    'store_id' => (int) $order->getStoreId(),
                ],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error creating spend transaction after order placement',
                [
                    'order_id' => $orderId,
                    'exception' => $e,
                ],
            );
        }
    }

    /**
     * Check whether a spend_order transaction already exists for this order
     *
     * @param int $orderId
     * @param int $customerId
     * @return bool
     */
    private function hasExistingSpendTransaction(int $orderId, int $customerId): bool
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TransactionInterface::ORDER_ID, $orderId)
                ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_SPEND_ORDER)
                ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
                ->create();

            $results = $this->transactionRepository->getList($searchCriteria);

            return $results->getTotalCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

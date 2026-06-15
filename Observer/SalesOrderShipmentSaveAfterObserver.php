<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Shipment;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;
use Psr\Log\LoggerInterface;

/**
 * Activates pending earn transactions when a shipment is saved
 */
class SalesOrderShipmentSaveAfterObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TransactionResource $transactionResource
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly TransactionResource $transactionResource,
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

        $holdingDays = $this->config->getHoldingDays();

        if ($holdingDays <= 0) {
            return;
        }

        /** @var Shipment $shipment */
        $shipment = $observer->getEvent()->getShipment();

        if (!$shipment) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $shipment->getOrder();

        if (!$order) {
            return;
        }

        $orderId = (int) $order->getEntityId();
        $customerId = (int) $order->getCustomerId();

        if (!$customerId) {
            return;
        }

        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TransactionInterface::ORDER_ID, $orderId)
                ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_EARN_ORDER)
                ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
                ->addFilter(TransactionInterface::STATUS, TransactionInterface::STATUS_PENDING)
                ->create();

            $results = $this->transactionRepository->getList($searchCriteria);
            $transactions = $results->getItems();

            if (empty($transactions)) {
                return;
            }

            $connection = $this->transactionResource->getConnection();
            $transactionIds = array_map(
                static fn($t) => $t->getTransactionId(),
                $transactions,
            );

            $connection->update(
                $this->transactionResource->getMainTable(),
                ['status' => TransactionInterface::STATUS_ACTIVE],
                ['transaction_id IN (?)' => $transactionIds],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error activating pending transactions on shipment',
                [
                    'order_id' => $orderId,
                    'exception' => $e,
                ],
            );
        }
    }
}

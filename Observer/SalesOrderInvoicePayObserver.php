<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order\Invoice;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\OrderEarningCalculator;
use Meetanshi\RewardPoints\Model\Rule\Validator\EarningValidator;
use Psr\Log\LoggerInterface;

/**
 * Awards reward points when an invoice is paid
 */
class SalesOrderInvoicePayObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param EarningValidator $earningValidator
     * @param OrderEarningCalculator $orderEarningCalculator
     * @param BalanceManagementInterface $balanceManagement
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly EarningValidator $earningValidator,
        private readonly OrderEarningCalculator $orderEarningCalculator,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly StoreManagerInterface $storeManager,
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
        if (!$this->config->isEarnAfterInvoice()) {
            return;
        }

        /** @var Invoice $invoice */
        $invoice = $observer->getEvent()->getInvoice();

        if (!$invoice) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $invoice->getOrder();

        if (!$order) {
            return;
        }

        $customerId = (int) $order->getCustomerId();

        if (!$this->earningValidator->isEligible($order, $customerId)) {
            return;
        }

        $orderId = (int) $order->getEntityId();

        if ($this->hasExistingEarnTransaction($orderId, $customerId)) {
            return;
        }

        try {
            $websiteId = (int) $this->storeManager->getStore((int) $order->getStoreId())->getWebsiteId();
            $points = $this->orderEarningCalculator->calculateOrderPoints($order, $websiteId, $customerId);

            if ($points <= 0) {
                return;
            }

            $holdingDays = $this->config->getHoldingDays((int) $order->getStoreId());
            $status = $holdingDays > 0 ? TransactionInterface::STATUS_PENDING : TransactionInterface::STATUS_ACTIVE;

            $this->balanceManagement->addPoints(
                $customerId,
                $websiteId,
                $points,
                TransactionInterface::ACTION_EARN_ORDER,
                (string) __('Points earned from order #%1', $order->getIncrementId()),
                null,
                true,
                [
                    'order_id' => $orderId,
                    'store_id' => (int) $order->getStoreId(),
                ],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error awarding points on invoice pay',
                [
                    'order_id' => $orderId,
                    'exception' => $e,
                ],
            );
        }
    }

    /**
     * Check whether an earn_order transaction already exists for this order
     *
     * @param int $orderId
     * @param int $customerId
     * @return bool
     */
    private function hasExistingEarnTransaction(int $orderId, int $customerId): bool
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TransactionInterface::ORDER_ID, $orderId)
                ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_EARN_ORDER)
                ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
                ->create();

            $results = $this->transactionRepository->getList($searchCriteria);

            return $results->getTotalCount() > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}

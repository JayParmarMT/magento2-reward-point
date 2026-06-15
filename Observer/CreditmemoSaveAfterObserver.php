<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Api\Data\CreditmemoInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;
use Psr\Log\LoggerInterface;

/**
 * Orchestrates reward points adjustments after a credit memo is saved
 */
class CreditmemoSaveAfterObserver implements ObserverInterface
{
    /**
     * @param Config $config
     * @param BalanceManagementInterface $balanceManagement
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param TransactionResource $transactionResource
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Config $config,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly TransactionResource $transactionResource,
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
        if (!$this->config->isEnabled()) {
            return;
        }

        /** @var CreditmemoInterface $creditmemo */
        $creditmemo = $observer->getEvent()->getCreditmemo();

        if (!$creditmemo) {
            return;
        }

        /** @var OrderInterface $order */
        $order = $creditmemo->getOrder();

        if (!$order) {
            return;
        }

        $customerId = (int) $order->getCustomerId();
        $orderId = (int) $order->getEntityId();
        $creditmemoId = (int) $creditmemo->getEntityId();
        $storeId = (int) $order->getStoreId();

        if (!$customerId) {
            return;
        }

        try {
            $websiteId = (int) $this->storeManager->getStore($storeId)->getWebsiteId();

            if ($this->config->isPointRefundEnabled($storeId)) {
                $this->cancelEarnedPoints($orderId, $creditmemoId, $customerId, $websiteId, $order, $creditmemo);
                $this->cancelTierBonusPoints($orderId, $creditmemoId, $customerId, $websiteId, $order, $creditmemo);
            }

            if ($this->config->isRestoreSpentOnRefund($storeId)) {
                $this->restoreSpentPoints($orderId, $creditmemoId, $customerId, $websiteId, $order, $creditmemo);
            }
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error processing credit memo reward points',
                [
                    'creditmemo_id' => $creditmemoId,
                    'order_id' => $orderId,
                    'exception' => $e,
                ],
            );
        }
    }

    /**
     * Cancel earned points proportionally based on refund amount
     *
     * @param int $orderId
     * @param int $creditmemoId
     * @param int $customerId
     * @param int $websiteId
     * @param OrderInterface $order
     * @param CreditmemoInterface $creditmemo
     * @return void
     * @throws \Exception
     */
    private function cancelEarnedPoints(
        int $orderId,
        int $creditmemoId,
        int $customerId,
        int $websiteId,
        OrderInterface $order,
        CreditmemoInterface $creditmemo,
    ): void {
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(TransactionInterface::ORDER_ID, $orderId)
            ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_EARN_ORDER)
            ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
            ->create();

        $results = $this->transactionRepository->getList($searchCriteria);
        $earnTransactions = $results->getItems();

        if (empty($earnTransactions)) {
            return;
        }

        $earnTransaction = reset($earnTransactions);
        $status = $earnTransaction->getStatus();

        if (in_array($status, [TransactionInterface::STATUS_CANCELLED, TransactionInterface::STATUS_EXPIRED], true)) {
            return;
        }

        $earnedPoints = $earnTransaction->getPointsDelta();

        if ($earnedPoints <= 0) {
            return;
        }

        $orderSubtotal = (float) $order->getBaseSubtotal();
        $refundAmount = (float) $creditmemo->getBaseSubtotal();

        if ($orderSubtotal <= 0) {
            $pointsToCancel = $earnedPoints;
        } else {
            $ratio = min(1.0, $refundAmount / $orderSubtotal);
            $pointsToCancel = (int) floor($earnedPoints * $ratio);
        }

        if ($pointsToCancel <= 0) {
            return;
        }

        $connection = $this->transactionResource->getConnection();

        if ($pointsToCancel >= $earnedPoints) {
            $connection->update(
                $this->transactionResource->getMainTable(),
                ['status' => TransactionInterface::STATUS_CANCELLED],
                ['transaction_id = ?' => $earnTransaction->getTransactionId()],
            );
        }

        $this->balanceManagement->subtractPoints(
            $customerId,
            $websiteId,
            $pointsToCancel,
            TransactionInterface::ACTION_REFUND_EARN,
            (string) __('Points cancelled due to refund on order #%1', $order->getIncrementId()),
            [
                'order_id' => $orderId,
                'creditmemo_id' => $creditmemoId,
            ],
        );
    }

    /**
     * Cancel tier-bonus points proportionally when the earn is being refunded.
     *
     * Tier bonus transactions share the same order_id as the original earn_order
     * transaction.  We find all tier_up transactions for this order and subtract
     * the same proportional amount that was applied to earned points.
     *
     * Guards against double-execution: skips if a refund_tier_bonus transaction
     * already exists for this creditmemo.
     *
     * @param int $orderId
     * @param int $creditmemoId
     * @param int $customerId
     * @param int $websiteId
     * @param OrderInterface $order
     * @param CreditmemoInterface $creditmemo
     * @return void
     * @throws \Exception
     */
    private function cancelTierBonusPoints(
        int $orderId,
        int $creditmemoId,
        int $customerId,
        int $websiteId,
        OrderInterface $order,
        CreditmemoInterface $creditmemo,
    ): void {
        // Guard: skip if already processed for this creditmemo
        $existingSearchCriteria = $this->searchCriteriaBuilder
            ->addFilter(TransactionInterface::CREDITMEMO_ID, $creditmemoId)
            ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_REFUND_TIER_BONUS)
            ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
            ->create();

        if ($this->transactionRepository->getList($existingSearchCriteria)->getTotalCount() > 0) {
            return;
        }

        // Find all tier_up transactions linked to this order
        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(TransactionInterface::ORDER_ID, $orderId)
            ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_TIER_UP)
            ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
            ->create();

        $tierTransactions = $this->transactionRepository->getList($searchCriteria)->getItems();

        if (empty($tierTransactions)) {
            return;
        }

        // Calculate total tier bonus points awarded for this order
        $totalTierBonus = 0;

        foreach ($tierTransactions as $tierTxn) {
            $delta = (int) $tierTxn->getPointsDelta();

            if ($delta > 0) {
                $totalTierBonus += $delta;
            }
        }

        if ($totalTierBonus <= 0) {
            return;
        }

        // Apply same refund ratio as earn cancellation
        $orderSubtotal = (float) $order->getBaseSubtotal();
        $refundAmount  = (float) $creditmemo->getBaseSubtotal();

        if ($orderSubtotal <= 0) {
            $pointsToCancel = $totalTierBonus;
        } else {
            $ratio          = min(1.0, $refundAmount / $orderSubtotal);
            $pointsToCancel = (int) floor($totalTierBonus * $ratio);
        }

        if ($pointsToCancel <= 0) {
            return;
        }

        // Mark original tier_up transactions as cancelled on full refund
        $connection = $this->transactionResource->getConnection();

        if ($pointsToCancel >= $totalTierBonus) {
            foreach ($tierTransactions as $tierTxn) {
                if ((int) $tierTxn->getPointsDelta() > 0) {
                    $connection->update(
                        $this->transactionResource->getMainTable(),
                        ['status' => TransactionInterface::STATUS_CANCELLED],
                        ['transaction_id = ?' => $tierTxn->getTransactionId()],
                    );
                }
            }
        }

        $this->balanceManagement->subtractPoints(
            $customerId,
            $websiteId,
            $pointsToCancel,
            TransactionInterface::ACTION_REFUND_TIER_BONUS,
            (string) __('Tier bonus cancelled due to refund on order #%1', $order->getIncrementId()),
            [
                'order_id'     => $orderId,
                'creditmemo_id' => $creditmemoId,
            ],
        );
    }

    /**
     * Restore spent points proportionally based on refund amount
     *
     * @param int $orderId
     * @param int $creditmemoId
     * @param int $customerId
     * @param int $websiteId
     * @param OrderInterface $order
     * @param CreditmemoInterface $creditmemo
     * @return void
     * @throws \Exception
     */
    private function restoreSpentPoints(
        int $orderId,
        int $creditmemoId,
        int $customerId,
        int $websiteId,
        OrderInterface $order,
        CreditmemoInterface $creditmemo,
    ): void {
        $existingSearchCriteria = $this->searchCriteriaBuilder
            ->addFilter(TransactionInterface::CREDITMEMO_ID, $creditmemoId)
            ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_REFUND_SPEND)
            ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
            ->create();

        $existingResults = $this->transactionRepository->getList($existingSearchCriteria);

        if ($existingResults->getTotalCount() > 0) {
            return;
        }

        $searchCriteria = $this->searchCriteriaBuilder
            ->addFilter(TransactionInterface::ORDER_ID, $orderId)
            ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_SPEND_ORDER)
            ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
            ->create();

        $results = $this->transactionRepository->getList($searchCriteria);
        $spendTransactions = $results->getItems();

        if (empty($spendTransactions)) {
            return;
        }

        $spendTransaction = reset($spendTransactions);
        $spentPoints = abs($spendTransaction->getPointsDelta());

        if ($spentPoints <= 0) {
            return;
        }

        $orderSubtotal = (float) $order->getBaseSubtotal();
        $refundAmount = (float) $creditmemo->getBaseSubtotal();

        if ($orderSubtotal <= 0) {
            $pointsToRestore = $spentPoints;
        } else {
            $ratio = min(1.0, $refundAmount / $orderSubtotal);
            $pointsToRestore = (int) floor($spentPoints * $ratio);
        }

        if ($pointsToRestore <= 0) {
            return;
        }

        $this->balanceManagement->addPoints(
            $customerId,
            $websiteId,
            $pointsToRestore,
            TransactionInterface::ACTION_REFUND_SPEND,
            (string) __('Points restored due to refund on order #%1', $order->getIncrementId()),
            null,
            false,
            [
                'order_id' => $orderId,
                'creditmemo_id' => $creditmemoId,
            ],
        );
    }
}

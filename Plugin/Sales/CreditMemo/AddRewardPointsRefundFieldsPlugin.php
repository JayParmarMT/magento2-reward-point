<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Plugin\Sales\CreditMemo;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Sales\Model\Order\Creditmemo;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Adds reward_point_refund and refund_to_points fields to credit memo data
 */
class AddRewardPointsRefundFieldsPlugin
{
    /**
     * @param Config $config
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     */
    public function __construct(
        private readonly Config $config,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
    ) {
    }

    /**
     * Add reward points fields before credit memo operations
     *
     * @param Creditmemo $subject
     * @return void
     */
    public function beforeRefund(Creditmemo $subject): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $order = $subject->getOrder();

        if (!$order) {
            return;
        }

        $customerId = (int) $order->getCustomerId();
        $orderId = (int) $order->getEntityId();

        if (!$customerId || !$orderId) {
            return;
        }

        if ($subject->getData('reward_point_refund') === null) {
            $subject->setData('reward_point_refund', 0);
        }

        if ($subject->getData('refund_to_points') === null) {
            $subject->setData('refund_to_points', false);
        }

        $maxRefundPoints = $this->getMaxRefundablePoints($orderId, $customerId);
        $subject->setData('max_reward_point_refund', $maxRefundPoints);
    }

    /**
     * Get maximum points that can be refunded (= points spent on the order)
     *
     * @param int $orderId
     * @param int $customerId
     * @return int
     */
    private function getMaxRefundablePoints(int $orderId, int $customerId): int
    {
        try {
            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter(TransactionInterface::ORDER_ID, $orderId)
                ->addFilter(TransactionInterface::ACTION_CODE, TransactionInterface::ACTION_SPEND_ORDER)
                ->addFilter(TransactionInterface::CUSTOMER_ID, $customerId)
                ->create();

            $results = $this->transactionRepository->getList($searchCriteria);
            $transactions = $results->getItems();

            if (empty($transactions)) {
                return 0;
            }

            $spendTransaction = reset($transactions);

            return abs($spendTransaction->getPointsDelta());
        } catch (\Exception $e) {
            return 0;
        }
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\Order\Creditmemo;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Credit memo reward points refund fields block
 */
class RewardPoints extends Template
{
    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        array $data = [],
    ) {
        parent::__construct($context, $data);
        $this->_template = 'Meetanshi_RewardPoints::order/creditmemo/rewardpoints.phtml';
    }

    /**
     * Check if reward points module is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    /**
     * Get the credit memo from registry
     *
     * @return \Magento\Sales\Model\Order\Creditmemo|null
     */
    public function getCreditmemo(): ?\Magento\Sales\Model\Order\Creditmemo
    {
        return $this->registry->registry('current_creditmemo');
    }

    /**
     * Get number of points spent on this order that can be refunded
     *
     * @return int
     */
    public function getSpentPoints(): int
    {
        $creditmemo = $this->getCreditmemo();

        if (!$creditmemo) {
            return 0;
        }

        $order = $creditmemo->getOrder();

        if (!$order) {
            return 0;
        }

        $customerId = (int) $order->getCustomerId();
        $orderId = (int) $order->getEntityId();

        if (!$customerId || !$orderId) {
            return 0;
        }

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

    /**
     * Check if restore spent on refund is configured
     *
     * @return bool
     */
    public function isRestoreSpentEnabled(): bool
    {
        return $this->config->isRestoreSpentOnRefund();
    }

    /**
     * Check if cancel earned on refund is configured
     *
     * @return bool
     */
    public function isCancelEarnedEnabled(): bool
    {
        return $this->config->isPointRefundEnabled();
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\Transaction;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;

/**
 * Transaction View Block
 */
class View extends Template
{
    /**
     * @param Context $context
     * @param TransactionRepositoryInterface $transactionRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get current transaction
     *
     * @return TransactionInterface|null
     */
    public function getTransaction(): ?TransactionInterface
    {
        $transactionId = (int) $this->getRequest()->getParam('transaction_id');

        if (!$transactionId) {
            return null;
        }

        try {
            return $this->transactionRepository->getById($transactionId);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get customer name for transaction
     *
     * @param TransactionInterface $transaction
     * @return string
     */
    public function getCustomerName(TransactionInterface $transaction): string
    {
        try {
            $customer = $this->customerRepository->getById($transaction->getCustomerId());

            return trim($customer->getFirstname() . ' ' . $customer->getLastname());
        } catch (\Exception) {
            return (string) $transaction->getCustomerId();
        }
    }

    /**
     * Get back URL
     *
     * @return string
     */
    public function getBackUrl(): string
    {
        return $this->getUrl('meetanshi_rewardpoints/transaction/index');
    }

    /**
     * Get status label
     *
     * @param string $status
     * @return string
     */
    public function getStatusLabel(string $status): string
    {
        $labels = [
            TransactionInterface::STATUS_PENDING => __('Pending'),
            TransactionInterface::STATUS_ACTIVE => __('Active'),
            TransactionInterface::STATUS_EXPIRED => __('Expired'),
            TransactionInterface::STATUS_CANCELLED => __('Cancelled'),
        ];

        return (string) ($labels[$status] ?? $status);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory;

/**
 * Admin Transaction Mass Expire Controller
 * Force-expires selected active transactions by calling expirePoints().
 */
class MassExpire extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param BalanceManagementInterface $balanceManagement
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $collection->addFieldToFilter('status', TransactionInterface::STATUS_ACTIVE);
            $collection->addFieldToFilter('points_delta', ['gt' => 0]);

            // Group by customer_id to batch expire calls
            $byCustomer = [];

            foreach ($collection as $transaction) {
                $customerId = (int) $transaction->getCustomerId();
                $byCustomer[$customerId][] = (int) $transaction->getTransactionId();
            }

            $totalExpired = 0;
            $websiteId = (int) $this->storeManager->getWebsite()->getId();

            foreach ($byCustomer as $customerId => $transactionIds) {
                $count = $this->balanceManagement->expirePoints($customerId, $websiteId, $transactionIds);
                $totalExpired += $count;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 transaction(s) have been force-expired.', $totalExpired),
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $resultRedirect->setPath('*/*/');
    }

    /**
     * Check ACL permission
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory;

/**
 * Admin Transaction Mass Cancel Controller
 */
class MassCancel extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param TransactionResource $transactionResource
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly TransactionResource $transactionResource,
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
            $collection->addFieldToFilter('status', TransactionInterface::STATUS_PENDING);
            $cancelled = 0;

            foreach ($collection as $transaction) {
                $transaction->setStatus(TransactionInterface::STATUS_CANCELLED);
                $this->transactionResource->save($transaction);
                $cancelled++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 transaction(s) have been cancelled.', $cancelled),
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

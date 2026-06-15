<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;

/**
 * Admin Transaction Cancel Controller
 * Only cancels 'pending' transactions; does NOT affect balance.
 */
class Cancel extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param TransactionRepositoryInterface $transactionRepository
     * @param TransactionResource $transactionResource
     */
    public function __construct(
        Context $context,
        private readonly TransactionRepositoryInterface $transactionRepository,
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
        $transactionId = (int) $this->getRequest()->getParam('transaction_id');

        if (!$transactionId) {
            $this->messageManager->addErrorMessage(__('Transaction ID is required.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $transaction = $this->transactionRepository->getById($transactionId);

            if ($transaction->getStatus() !== TransactionInterface::STATUS_PENDING) {
                throw new LocalizedException(
                    __('Only pending transactions can be cancelled. Current status: %1', $transaction->getStatus()),
                );
            }

            $transaction->setStatus(TransactionInterface::STATUS_CANCELLED);
            $this->transactionResource->save($transaction);

            $this->messageManager->addSuccessMessage(__('Transaction #%1 has been cancelled.', $transactionId));
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('Transaction #%1 does not exist.', $transactionId));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while cancelling the transaction.'));
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

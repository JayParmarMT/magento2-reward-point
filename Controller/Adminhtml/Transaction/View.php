<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;

/**
 * Admin Transaction View Controller
 */
class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param TransactionRepositoryInterface $transactionRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly TransactionRepositoryInterface $transactionRepository,
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
        $transactionId = (int) $this->getRequest()->getParam('transaction_id');

        if (!$transactionId) {
            $this->messageManager->addErrorMessage(__('Transaction ID is required.'));
            $resultRedirect = $this->resultRedirectFactory->create();

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->transactionRepository->getById($transactionId);
        } catch (NoSuchEntityException) {
            $this->messageManager->addErrorMessage(__('This transaction no longer exists.'));
            $resultRedirect = $this->resultRedirectFactory->create();

            return $resultRedirect->setPath('*/*/');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Transaction #%1', $transactionId));

        return $resultPage;
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

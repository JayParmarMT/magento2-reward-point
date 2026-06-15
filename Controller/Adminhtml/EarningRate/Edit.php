<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\EarningRate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Api\EarningRateRepositoryInterface;

/**
 * Admin Earning Rate Edit Controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::earning_rate';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param EarningRateRepositoryInterface $earningRateRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly EarningRateRepositoryInterface $earningRateRepository,
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
        $rateId = (int) $this->getRequest()->getParam('rate_id');
        $isNew = $rateId === 0;

        if (!$isNew) {
            try {
                $this->earningRateRepository->getById($rateId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This earning rate no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $title = $isNew ? __('New Earning Rate') : __('Edit Earning Rate');
        $resultPage->getConfig()->getTitle()->prepend($title);

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

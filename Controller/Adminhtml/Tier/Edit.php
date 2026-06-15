<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Tier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;

/**
 * Admin Tier Edit Controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::tier';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param TierRepositoryInterface $tierRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly TierRepositoryInterface $tierRepository,
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
        $tierId = (int) $this->getRequest()->getParam('tier_id');

        if ($tierId) {
            try {
                $this->tierRepository->getById($tierId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This tier no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(
            $tierId ? __('Edit Reward Tier') : __('New Reward Tier'),
        );

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

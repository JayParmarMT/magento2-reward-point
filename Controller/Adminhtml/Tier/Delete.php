<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Tier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;

/**
 * Admin Tier Delete Controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::tier';

    /**
     * @param Context $context
     * @param TierRepositoryInterface $tierRepository
     */
    public function __construct(
        Context $context,
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
        $resultRedirect = $this->resultRedirectFactory->create();
        $tierId = (int) $this->getRequest()->getParam('tier_id');

        if ($tierId) {
            try {
                $this->tierRepository->deleteById($tierId);
                $this->messageManager->addSuccessMessage(__('The tier has been deleted.'));
            } catch (\Exception $e) {
                $this->messageManager->addErrorMessage($e->getMessage());

                return $resultRedirect->setPath('*/*/edit', ['tier_id' => $tierId]);
            }
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

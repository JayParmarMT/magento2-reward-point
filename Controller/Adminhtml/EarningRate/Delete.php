<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\EarningRate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Meetanshi\RewardPoints\Api\EarningRateRepositoryInterface;

/**
 * Admin Earning Rate Delete Controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::earning_rate';

    /**
     * @param Context $context
     * @param EarningRateRepositoryInterface $earningRateRepository
     */
    public function __construct(
        Context $context,
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
        $resultRedirect = $this->resultRedirectFactory->create();
        $rateId = (int) $this->getRequest()->getParam('rate_id');

        if (!$rateId) {
            $this->messageManager->addErrorMessage(__('We can\'t find the earning rate to delete.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->earningRateRepository->deleteById($rateId);
            $this->messageManager->addSuccessMessage(__('The earning rate has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while deleting the earning rate.'),
            );
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

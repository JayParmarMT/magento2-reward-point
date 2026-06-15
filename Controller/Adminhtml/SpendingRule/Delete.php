<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\SpendingRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Meetanshi\RewardPoints\Api\SpendingRuleRepositoryInterface;

/**
 * Admin Spending Rule Delete Controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::spending_rule';

    /**
     * @param Context $context
     * @param SpendingRuleRepositoryInterface $spendingRuleRepository
     */
    public function __construct(
        Context $context,
        private readonly SpendingRuleRepositoryInterface $spendingRuleRepository,
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
        $ruleId = (int) $this->getRequest()->getParam('rule_id');

        if (!$ruleId) {
            $this->messageManager->addErrorMessage(__('Unable to find a spending rule to delete.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->spendingRuleRepository->deleteById($ruleId);
            $this->messageManager->addSuccessMessage(__('The spending rule has been deleted.'));
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

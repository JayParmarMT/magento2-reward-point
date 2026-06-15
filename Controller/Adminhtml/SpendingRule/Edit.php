<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\SpendingRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Api\SpendingRuleRepositoryInterface;

/**
 * Admin Spending Rule Edit Controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::spending_rule';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param SpendingRuleRepositoryInterface $spendingRuleRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
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
        $ruleId = (int) $this->getRequest()->getParam('rule_id');

        if ($ruleId) {
            try {
                $this->spendingRuleRepository->getById($ruleId);
            } catch (NoSuchEntityException) {
                $this->messageManager->addErrorMessage(__('This spending rule no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $title = $ruleId
            ? __('Edit Cart Spending Rule')
            : __('New Cart Spending Rule');
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

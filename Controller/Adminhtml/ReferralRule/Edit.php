<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\ReferralRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Api\ReferralRuleRepositoryInterface;

/**
 * Admin Referral Rule Edit Controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::referral_rule';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param ReferralRuleRepositoryInterface $ruleRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly ReferralRuleRepositoryInterface $ruleRepository,
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
                $this->ruleRepository->getById($ruleId);
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This referral rule no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(
            $ruleId ? __('Edit Referral Rule') : __('New Referral Rule'),
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

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\BehaviorRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Api\BehaviorRuleRepositoryInterface;

/**
 * Edit Behavior Rule controller
 */
class Edit extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::behavior_rule';

    /**
     * @param Context $context
     * @param PageFactory $resultPageFactory
     * @param BehaviorRuleRepositoryInterface $ruleRepository
     */
    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly BehaviorRuleRepositoryInterface $ruleRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\View\Result\Page|\Magento\Framework\Controller\Result\Redirect
    {
        $ruleId = (int) $this->getRequest()->getParam('rule_id');

        if ($ruleId) {
            try {
                $rule = $this->ruleRepository->getById($ruleId);
                $title = __('Edit Behavior Earning Rule: %1', $rule->getName());
            } catch (NoSuchEntityException $e) {
                $this->messageManager->addErrorMessage(__('This behavior rule no longer exists.'));
                $resultRedirect = $this->resultRedirectFactory->create();

                return $resultRedirect->setPath('*/*/');
            }
        } else {
            $title = __('New Behavior Earning Rule');
        }

        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Meetanshi_RewardPoints::behavior_rule');
        $resultPage->getConfig()->getTitle()->prepend(__('Behavior Earning Rules'));
        $resultPage->getConfig()->getTitle()->prepend($title);

        return $resultPage;
    }
}

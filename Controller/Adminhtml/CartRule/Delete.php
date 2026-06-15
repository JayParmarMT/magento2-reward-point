<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\CartRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Meetanshi\RewardPoints\Api\CartRuleRepositoryInterface;

/**
 * Delete Cart Rule controller
 */
class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::cart_rule';

    /**
     * @param Context $context
     * @param CartRuleRepositoryInterface $ruleRepository
     */
    public function __construct(
        Context $context,
        private readonly CartRuleRepositoryInterface $ruleRepository,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute(): \Magento\Framework\Controller\Result\Redirect
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $ruleId = (int) $this->getRequest()->getParam('rule_id');

        if (!$ruleId) {
            $this->messageManager->addErrorMessage(__('Invalid rule ID.'));

            return $resultRedirect->setPath('*/*/');
        }

        try {
            $this->ruleRepository->deleteById($ruleId);
            $this->messageManager->addSuccessMessage(__('The cart earning rule has been deleted.'));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while deleting the rule.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}

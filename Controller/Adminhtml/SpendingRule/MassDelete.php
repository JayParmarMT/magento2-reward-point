<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\SpendingRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\SpendingRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\CollectionFactory;

/**
 * Admin Spending Rule Mass Delete Controller
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::spending_rule';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param SpendingRuleRepositoryInterface $spendingRuleRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;

            foreach ($collection as $rule) {
                $this->spendingRuleRepository->delete($rule);
                $deleted++;
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 spending rule(s) have been deleted.', $deleted));
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

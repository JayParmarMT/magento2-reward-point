<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\ReferralRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\ReferralRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\ReferralRule\CollectionFactory;

/**
 * Admin Referral Rule Mass Status Update Controller
 */
class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::referral_rule';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param ReferralRuleRepositoryInterface $ruleRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
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
        $resultRedirect = $this->resultRedirectFactory->create();
        $status = (int) $this->getRequest()->getParam('status');

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $updated = 0;

            foreach ($collection->getItems() as $rule) {
                $rule->setIsActive((bool) $status);
                $this->ruleRepository->save($rule);
                $updated++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 referral rule(s) have been updated.', $updated),
            );
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

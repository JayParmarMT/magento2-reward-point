<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\BehaviorRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\BehaviorRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\BehaviorRule\CollectionFactory;

/**
 * Mass Delete Behavior Rules controller
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::behavior_rule';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param BehaviorRuleRepositoryInterface $ruleRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly BehaviorRuleRepositoryInterface $ruleRepository,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;

            foreach ($collection->getItems() as $rule) {
                $this->ruleRepository->deleteById((int) $rule->getId());
                $deleted++;
            }

            $this->messageManager->addSuccessMessage(__('A total of %1 record(s) have been deleted.', $deleted));
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong during mass delete.'));
        }

        return $resultRedirect->setPath('*/*/');
    }
}

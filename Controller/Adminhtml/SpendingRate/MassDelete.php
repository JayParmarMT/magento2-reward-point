<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\SpendingRate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate\CollectionFactory;

/**
 * Admin Spending Rate Mass Delete Controller
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::spending_rate';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
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
            $deletedCount = 0;

            foreach ($collection->getItems() as $item) {
                $this->spendingRateRepository->deleteById((int) $item->getId());
                $deletedCount++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 spending rate(s) have been deleted.', $deletedCount),
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while deleting the spending rates.'),
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

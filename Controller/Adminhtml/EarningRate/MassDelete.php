<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\EarningRate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Ui\Component\MassAction\Filter;
use Meetanshi\RewardPoints\Api\EarningRateRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate\CollectionFactory;

/**
 * Admin Earning Rate Mass Delete Controller
 */
class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::earning_rate';

    /**
     * @param Context $context
     * @param Filter $filter
     * @param CollectionFactory $collectionFactory
     * @param EarningRateRepositoryInterface $earningRateRepository
     */
    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
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

        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deletedCount = 0;

            foreach ($collection->getItems() as $item) {
                $this->earningRateRepository->deleteById((int) $item->getId());
                $deletedCount++;
            }

            $this->messageManager->addSuccessMessage(
                __('A total of %1 earning rate(s) have been deleted.', $deletedCount),
            );
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while deleting the earning rates.'),
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

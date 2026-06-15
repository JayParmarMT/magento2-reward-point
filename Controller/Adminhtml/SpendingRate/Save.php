<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\SpendingRate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Model\SpendingRateFactory;

/**
 * Admin Spending Rate Save Controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::spending_rate';

    /**
     * @param Context $context
     * @param SpendingRateRepositoryInterface $spendingRateRepository
     * @param SpendingRateFactory $spendingRateFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        private readonly SpendingRateRepositoryInterface $spendingRateRepository,
        private readonly SpendingRateFactory $spendingRateFactory,
        private readonly DataPersistorInterface $dataPersistor,
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
        $rawPost = $this->getRequest()->getPostValue();

        if (empty($rawPost)) {
            return $resultRedirect->setPath('*/*/');
        }

        // Magento UI forms with root dataScope="data" nest all field values under $_POST['data'].
        // Fall back to the raw POST array for robustness (e.g. custom AJAX submissions).
        $postData = (isset($rawPost['data']) && is_array($rawPost['data'])) ? $rawPost['data'] : $rawPost;

        $rateId = isset($postData['rate_id']) && $postData['rate_id']
            ? (int) $postData['rate_id']
            : null;

        try {
            $this->validatePostData($postData);
            $spendingRate = $this->loadOrCreateRate($rateId);
            $this->populateRate($spendingRate, $postData);

            $websiteIds = $this->extractIds($postData['website_ids'] ?? []);
            $customerGroupIds = $this->extractIds($postData['customer_group_ids'] ?? []);

            $this->spendingRateRepository->save($spendingRate, $websiteIds, $customerGroupIds);

            $this->messageManager->addSuccessMessage(__('The spending rate has been saved.'));
            $this->dataPersistor->clear('meetanshi_rewardpoints_spending_rate');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['rate_id' => $spendingRate->getRateId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the spending rate.'),
            );
        }

        $this->dataPersistor->set('meetanshi_rewardpoints_spending_rate', $postData);

        if ($rateId !== null) {
            return $resultRedirect->setPath('*/*/edit', ['rate_id' => $rateId]);
        }

        return $resultRedirect->setPath('*/*/new');
    }

    /**
     * Validate post data
     *
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    private function validatePostData(array $data): void
    {
        $points = isset($data['points']) ? (int) $data['points'] : 0;

        if ($points <= 0) {
            throw new LocalizedException(__('Points must be greater than zero.'));
        }

        $currencyAmount = isset($data['currency_amount']) ? (float) $data['currency_amount'] : 0.0;

        if ($currencyAmount <= 0) {
            throw new LocalizedException(__('Currency Amount must be greater than zero.'));
        }

        $minPoints = isset($data['min_points_per_order']) ? (int) $data['min_points_per_order'] : 0;

        if ($minPoints < 0) {
            throw new LocalizedException(__('Minimum Points Per Order must be zero or greater.'));
        }

        $priority = isset($data['priority']) ? (int) $data['priority'] : 0;

        if ($priority < 0) {
            throw new LocalizedException(__('Priority must be zero or greater.'));
        }

        // website_ids and customer_group_ids are optional; empty means applies to all.
    }

    /**
     * Load existing rate or create a new one
     *
     * @param int|null $rateId
     * @return SpendingRateInterface
     * @throws NoSuchEntityException
     */
    private function loadOrCreateRate(?int $rateId): SpendingRateInterface
    {
        if ($rateId !== null) {
            return $this->spendingRateRepository->getById($rateId);
        }

        return $this->spendingRateFactory->create();
    }

    /**
     * Populate rate model with post data
     *
     * @param SpendingRateInterface $rate
     * @param array $data
     * @return void
     */
    private function populateRate(SpendingRateInterface $rate, array $data): void
    {
        $rate->setPoints((int) ($data['points'] ?? 0));
        $rate->setCurrencyAmount((float) ($data['currency_amount'] ?? 0));
        $rate->setMinPointsPerOrder((int) ($data['min_points_per_order'] ?? 0));
        $rate->setPriority((int) ($data['priority'] ?? 0));
        $rate->setIsActive((bool) ($data['is_active'] ?? 1));
    }

    /**
     * Extract integer IDs from a value that may be an array or comma-separated string
     *
     * @param mixed $value
     * @return int[]
     */
    private function extractIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_map('intval', array_filter($value, static fn ($v) => $v !== ''));
        }

        if (is_string($value) && $value !== '') {
            return array_map('intval', explode(',', $value));
        }

        return [];
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

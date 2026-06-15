<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\EarningRate;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;
use Meetanshi\RewardPoints\Api\EarningRateRepositoryInterface;
use Meetanshi\RewardPoints\Model\EarningRateFactory;

/**
 * Admin Earning Rate Save Controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::earning_rate';

    /**
     * @param Context $context
     * @param EarningRateRepositoryInterface $earningRateRepository
     * @param EarningRateFactory $earningRateFactory
     * @param DataPersistorInterface $dataPersistor
     */
    public function __construct(
        Context $context,
        private readonly EarningRateRepositoryInterface $earningRateRepository,
        private readonly EarningRateFactory $earningRateFactory,
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
            $earningRate = $this->loadOrCreateRate($rateId);
            $this->populateRate($earningRate, $postData);

            $websiteIds = $this->extractIds($postData['website_ids'] ?? []);
            $customerGroupIds = $this->extractIds($postData['customer_group_ids'] ?? []);

            $this->earningRateRepository->save($earningRate, $websiteIds, $customerGroupIds);

            $this->messageManager->addSuccessMessage(__('The earning rate has been saved.'));
            $this->dataPersistor->clear('meetanshi_rewardpoints_earning_rate');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['rate_id' => $earningRate->getRateId()]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the earning rate.'),
            );
        }

        $this->dataPersistor->set('meetanshi_rewardpoints_earning_rate', $postData);

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
        $moneyStep = isset($data['money_step']) ? (float) $data['money_step'] : 0.0;

        if ($moneyStep <= 0) {
            throw new LocalizedException(__('Money Step must be greater than zero.'));
        }

        $rawPoints = isset($data['points']) ? (float) $data['points'] : 0.0;

        if ($rawPoints <= 0) {
            throw new LocalizedException(__('Points must be greater than zero.'));
        }

        $roundedPoints = $rawPoints >= 0.5 ? (int) ceil($rawPoints) : (int) floor($rawPoints);

        if ($roundedPoints < 1) {
            throw new LocalizedException(
                __('Points value is too small. After rounding it results in 0 points.'),
            );
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
     * @return EarningRateInterface
     * @throws NoSuchEntityException
     */
    private function loadOrCreateRate(?int $rateId): EarningRateInterface
    {
        if ($rateId !== null) {
            return $this->earningRateRepository->getById($rateId);
        }

        return $this->earningRateFactory->create();
    }

    /**
     * Populate rate model with post data
     *
     * @param EarningRateInterface $rate
     * @param array $data
     * @return void
     */
    private function populateRate(EarningRateInterface $rate, array $data): void
    {
        $rawPoints = (float) ($data['points'] ?? 0);
        $points = $rawPoints >= 0.5 ? (int) ceil($rawPoints) : (int) floor($rawPoints);

        $rate->setMoneyStep((float) ($data['money_step'] ?? 0));
        $rate->setPoints($points);
        $rate->setPriority((int) ($data['priority'] ?? 0));
        $rate->setIsActive((bool) ($data['is_active'] ?? 1));

        $minOrderTotal = isset($data['min_order_total']) && $data['min_order_total'] !== ''
            ? (float) $data['min_order_total']
            : null;

        $rate->setMinOrderTotal($minOrderTotal);
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

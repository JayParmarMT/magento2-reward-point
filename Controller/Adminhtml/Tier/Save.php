<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Tier;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Model\TierFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier\CollectionFactory as TierCollectionFactory;

/**
 * Admin Tier Save Controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::tier';

    private const RULE_TYPE = 'tier';

    /**
     * @param Context $context
     * @param TierRepositoryInterface $tierRepository
     * @param TierFactory $tierFactory
     * @param TierCollectionFactory $tierCollectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly TierFactory $tierFactory,
        private readonly TierCollectionFactory $tierCollectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly ResourceConnection $resourceConnection,
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

        $tierId = isset($postData['tier_id']) && $postData['tier_id']
            ? (int) $postData['tier_id']
            : null;

        try {
            $this->validatePostData($postData, $tierId);

            $tier = $tierId
                ? $this->tierRepository->getById($tierId)
                : $this->tierFactory->create();

            $tier->setName((string) ($postData['name'] ?? ''));
            $tier->setDescription($postData['description'] ?? null);
            $tier->setIsActive((bool) ($postData['is_active'] ?? 1));
            $tier->setMinPoints((int) ($postData['min_points'] ?? 0));
            $tier->setMinOrders((int) ($postData['min_orders'] ?? 0));
            $tier->setSortOrder((int) ($postData['sort_order'] ?? 0));
            $tier->setEarningBonusPercent((float) ($postData['earning_bonus_percent'] ?? 0));
            $tier->setBehaviorBonusPoints((int) ($postData['behavior_bonus_points'] ?? 0));
            $tier->setSpendingDiscountPercent((float) ($postData['spending_discount_percent'] ?? 0));
            $tier->setFreeShipping((bool) ($postData['free_shipping'] ?? 0));
            $tier->setEmailTemplate($postData['email_template'] ?? null);

            $linkedCartRuleId = isset($postData['linked_cart_rule_id']) && $postData['linked_cart_rule_id'] !== ''
                ? (int) $postData['linked_cart_rule_id']
                : null;
            $tier->setLinkedCartRuleId($linkedCartRuleId);

            if (!empty($postData['image'][0]['name'])) {
                $tier->setImage($postData['image'][0]['name']);
            } elseif (isset($postData['image'][0]['delete']) && $postData['image'][0]['delete']) {
                $tier->setImage(null);
            }

            $savedTier   = $this->tierRepository->save($tier);
            $savedTierId = (int) $savedTier->getTierId();

            $this->saveTierWebsites($savedTierId, $postData['website_ids'] ?? []);
            $this->saveTierCustomerGroups($savedTierId, $postData['customer_group_ids'] ?? []);

            $this->messageManager->addSuccessMessage(__('The tier has been saved.'));
            $this->dataPersistor->clear('meetanshi_rewardpoints_tier');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['tier_id' => $savedTierId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the tier.'),
            );
        }

        $this->dataPersistor->set('meetanshi_rewardpoints_tier', $postData);

        if ($tierId !== null) {
            return $resultRedirect->setPath('*/*/edit', ['tier_id' => $tierId]);
        }

        return $resultRedirect->setPath('*/*/new');
    }

    /**
     * Save tier-website associations
     *
     * @param int $tierId
     * @param array $websiteIds
     * @return void
     */
    private function saveTierWebsites(int $tierId, array $websiteIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');

        $connection->delete($table, [
            'rule_id = ?'   => $tierId,
            'rule_type = ?' => self::RULE_TYPE,
        ]);

        $rows = [];

        foreach ($websiteIds as $websiteId) {
            $rows[] = [
                'rule_id'    => $tierId,
                'rule_type'  => self::RULE_TYPE,
                'website_id' => (int) $websiteId,
            ];
        }

        if (!empty($rows)) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * Save tier-customer group associations
     *
     * @param int $tierId
     * @param array $groupIds
     * @return void
     */
    private function saveTierCustomerGroups(int $tierId, array $groupIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table      = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        $connection->delete($table, [
            'rule_id = ?'   => $tierId,
            'rule_type = ?' => self::RULE_TYPE,
        ]);

        $rows = [];

        foreach ($groupIds as $groupId) {
            $rows[] = [
                'rule_id'           => $tierId,
                'rule_type'         => self::RULE_TYPE,
                'customer_group_id' => (int) $groupId,
            ];
        }

        if (!empty($rows)) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * Validate post data
     *
     * @param array $data
     * @param int|null $currentTierId
     * @return void
     * @throws LocalizedException
     */
    private function validatePostData(array $data, ?int $currentTierId): void
    {
        if (empty(trim((string) ($data['name'] ?? '')))) {
            throw new LocalizedException(__('Name is required.'));
        }

        $minPoints = (int) ($data['min_points'] ?? 0);

        if ($minPoints < 0) {
            throw new LocalizedException(__('Minimum points must be zero or greater.'));
        }

        // First tier (lowest min_points among all) must be 0
        if ($minPoints === 0) {
            // Check if any other tier already has min_points = 0
            $collection = $this->tierCollectionFactory->create();
            $collection->addFieldToFilter('min_points', 0);

            if ($currentTierId) {
                $collection->addFieldToFilter('tier_id', ['neq' => $currentTierId]);
            }

            if ($collection->getSize() > 0) {
                throw new LocalizedException(
                    __('A tier with 0 minimum points already exists. Only one base tier is allowed.'),
                );
            }
        }

        $minOrders = (int) ($data['min_orders'] ?? 0);

        if ($minOrders < 0) {
            throw new LocalizedException(__('Minimum orders must be zero or greater.'));
        }

        $earningBonus = (float) ($data['earning_bonus_percent'] ?? 0);

        if ($earningBonus < 0) {
            throw new LocalizedException(__('Earning bonus percent must be zero or greater.'));
        }

        $spendingDiscount = (float) ($data['spending_discount_percent'] ?? 0);

        if ($spendingDiscount < 0 || $spendingDiscount > 100) {
            throw new LocalizedException(__('Spending discount percent must be between 0 and 100.'));
        }
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

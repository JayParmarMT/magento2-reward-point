<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\ReferralRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Meetanshi\RewardPoints\Api\ReferralRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleFactory;

/**
 * Admin Referral Rule Save Controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::referral_rule';

    private const RULE_TYPE = 'referral';

    /**
     * @param Context $context
     * @param ReferralRuleRepositoryInterface $ruleRepository
     * @param ReferralRuleFactory $ruleFactory
     * @param ReferralRuleConditionFactory $conditionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param ResourceConnection $resourceConnection
     * @param Json $json
     */
    public function __construct(
        Context $context,
        private readonly ReferralRuleRepositoryInterface $ruleRepository,
        private readonly ReferralRuleFactory $ruleFactory,
        private readonly ReferralRuleConditionFactory $conditionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
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
        $post = $this->getRequest()->getPostValue();

        if (empty($post)) {
            return $resultRedirect->setPath('*/*/');
        }

        // Magento UI forms with root dataScope="data" nest all field values under $_POST['data'].
        // Fall back to the raw POST array for robustness (e.g. custom AJAX submissions).
        $data = (isset($post['data']) && is_array($post['data'])) ? $post['data'] : $post;

        // Conditions are posted via the rule conditions block using data-form-part,
        // which bypasses the dataScope wrapper and arrives at root $_POST['rule'][...].
        // Merge them into $data['rule'] so the conditions serialization logic finds them.
        if (isset($post['rule']) && is_array($post['rule'])) {
            $data['rule'] = array_merge($data['rule'] ?? [], $post['rule']);
        }

        $ruleId = isset($data['rule_id']) && $data['rule_id']
            ? (int) $data['rule_id']
            : null;

        try {
            $this->validatePostData($data);

            $rule = $ruleId
                ? $this->ruleRepository->getById($ruleId)
                : $this->ruleFactory->create();

            $rule->setName((string) ($data['name'] ?? ''));
            $rule->setDescription($data['description'] ?? null);
            $rule->setIsActive((bool) ($data['is_active'] ?? 1));
            $rule->setFromDate($data['from_date'] ?: null);
            $rule->setToDate($data['to_date'] ?: null);
            $rule->setReferrerPoints((int) ($data['referrer_points'] ?? 0));
            $rule->setRefereePoints((int) ($data['referee_points'] ?? 0));
            $rule->setRefereeDiscount((float) ($data['referee_discount'] ?? 0));
            $rule->setDiscountType((string) ($data['discount_type'] ?? 'fixed'));
            $rule->setMaxInvitations((int) ($data['max_invitations'] ?? 0));

            // Convert the flat POST conditions array ({"1":{...},"1--1":{...}}) into the nested
            // format that AbstractModel::loadArray() understands ({"type":...,"conditions":[...]}).
            // Use ReferralRuleCondition (extends Magento\Rule\Model\AbstractModel) which has
            // loadPost() + _convertFlatToRecursive() built in, then read back via asArray().
            if (!empty($data['rule']['conditions']) && is_array($data['rule']['conditions'])) {
                $conditionModel = $this->conditionFactory->create();
                $conditionModel->loadPost(['conditions' => $data['rule']['conditions']]);
                $rule->setConditionsSerialized(
                    $this->json->serialize($conditionModel->getConditions()->asArray()),
                );
            } elseif (!empty($data['conditions_serialized'])) {
                $rule->setConditionsSerialized((string) $data['conditions_serialized']);
            }

            $savedRule = $this->ruleRepository->save($rule);
            $savedRuleId = (int) $savedRule->getRuleId();

            $this->saveRuleWebsites($savedRuleId, $data['website_ids'] ?? []);
            $this->saveRuleCustomerGroups($savedRuleId, $data['customer_group_ids'] ?? []);

            $this->messageManager->addSuccessMessage(__('The referral rule has been saved.'));
            $this->dataPersistor->clear('meetanshi_rewardpoints_referral_rule');

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['rule_id' => $savedRuleId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage(
                $e,
                __('Something went wrong while saving the referral rule.'),
            );
        }

        $this->dataPersistor->set('meetanshi_rewardpoints_referral_rule', $data);

        if ($ruleId !== null) {
            return $resultRedirect->setPath('*/*/edit', ['rule_id' => $ruleId]);
        }

        return $resultRedirect->setPath('*/*/new');
    }

    /**
     * Save rule-website associations
     *
     * @param int $ruleId
     * @param array $websiteIds
     * @return void
     */
    private function saveRuleWebsites(int $ruleId, array $websiteIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');

        $connection->delete($table, [
            'rule_id = ?' => $ruleId,
            'rule_type = ?' => self::RULE_TYPE,
        ]);

        $rows = [];

        foreach ($websiteIds as $websiteId) {
            $rows[] = [
                'rule_id'    => $ruleId,
                'rule_type'  => self::RULE_TYPE,
                'website_id' => (int) $websiteId,
            ];
        }

        if (!empty($rows)) {
            $connection->insertMultiple($table, $rows);
        }
    }

    /**
     * Save rule-customer group associations
     *
     * @param int $ruleId
     * @param array $groupIds
     * @return void
     */
    private function saveRuleCustomerGroups(int $ruleId, array $groupIds): void
    {
        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        $connection->delete($table, [
            'rule_id = ?' => $ruleId,
            'rule_type = ?' => self::RULE_TYPE,
        ]);

        $rows = [];

        foreach ($groupIds as $groupId) {
            $rows[] = [
                'rule_id'           => $ruleId,
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
     * @return void
     * @throws LocalizedException
     */
    private function validatePostData(array $data): void
    {
        if (empty(trim((string) ($data['name'] ?? '')))) {
            throw new LocalizedException(__('Name is required.'));
        }

        if ((int) ($data['referrer_points'] ?? 0) < 0) {
            throw new LocalizedException(__('Referrer points must be zero or greater.'));
        }

        if ((int) ($data['referee_points'] ?? 0) < 0) {
            throw new LocalizedException(__('Referee points must be zero or greater.'));
        }

        if ((float) ($data['referee_discount'] ?? 0) < 0) {
            throw new LocalizedException(__('Referee discount must be zero or greater.'));
        }

        $validDiscountTypes = ['fixed', 'percent'];

        if (!in_array((string) ($data['discount_type'] ?? 'fixed'), $validDiscountTypes, true)) {
            throw new LocalizedException(
                __('Invalid discount type. Must be one of: %1', implode(', ', $validDiscountTypes)),
            );
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

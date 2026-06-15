<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\BehaviorRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Meetanshi\RewardPoints\Api\BehaviorRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleFactory;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Source\BehaviorEvent;

/**
 * Save Behavior Rule controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::behavior_rule';

    /**
     * @param Context $context
     * @param BehaviorRuleRepositoryInterface $ruleRepository
     * @param BehaviorRuleFactory $ruleFactory
     * @param BehaviorRuleConditionFactory $conditionFactory
     * @param ResourceConnection $resourceConnection
     * @param Json $json
     */
    public function __construct(
        Context $context,
        private readonly BehaviorRuleRepositoryInterface $ruleRepository,
        private readonly BehaviorRuleFactory $ruleFactory,
        private readonly BehaviorRuleConditionFactory $conditionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly Json $json,
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

        try {
            $this->validatePostData($data);

            $ruleId = isset($data['rule_id']) ? (int) $data['rule_id'] : null;

            if ($ruleId) {
                $rule = $this->ruleRepository->getById($ruleId);
            } else {
                $rule = $this->ruleFactory->create();
            }

            $rule->setName((string) $data['name']);
            $rule->setDescription($data['description'] ?? null);
            $rule->setIsActive((bool) ($data['is_active'] ?? 0));
            $rule->setData('event_code', (string) $data['event_code']);
            $rule->setFromDate(!empty($data['from_date']) ? $data['from_date'] : null);
            $rule->setToDate(!empty($data['to_date']) ? $data['to_date'] : null);
            $rule->setPriority((int) ($data['priority'] ?? 0));
            $rule->setData('display_name', $data['display_name'] ?? null);
            $rule->setData('history_message', $data['history_message'] ?? null);
            $rule->setData('email_message', $data['email_message'] ?? null);
            $rule->setStopRulesProcessing((bool) ($data['stop_rules_processing'] ?? 0));
            $rule->setData('action_type', (string) ($data['action_type'] ?? 'fixed'));
            $rule->setPoints((int) $data['points']);
            $rule->setData('step', isset($data['step']) && $data['step'] !== '' ? (float) $data['step'] : null);
            $rule->setMaxPoints((int) ($data['max_points'] ?? 0));
            $rule->setData('expire_after_days', (int) ($data['expire_after_days'] ?? 0));
            $rule->setData('cap_per_day', (int) ($data['cap_per_day'] ?? 0));
            $rule->setData('cap_per_month', (int) ($data['cap_per_month'] ?? 0));
            $rule->setData('cap_per_year', (int) ($data['cap_per_year'] ?? 0));
            $rule->setData('cap_lifetime', (int) ($data['cap_lifetime'] ?? 0));

            // Convert the flat POST conditions array ({"1":{...},"1--1":{...}}) into the nested
            // format that AbstractModel::loadArray() understands ({"type":...,"conditions":[...]}).
            // Use CartRuleCondition (extends Magento\Rule\Model\AbstractModel) which has
            // loadPost() + _convertFlatToRecursive() built in, then read back via asArray().
            if (!empty($data['rule']['conditions']) && is_array($data['rule']['conditions'])) {
                $conditionModel = $this->conditionFactory->create();
                $conditionModel->loadPost(['conditions' => $data['rule']['conditions']]);
                $rule->setConditionsSerialized(
                    $this->json->serialize($conditionModel->getConditions()->asArray()),
                );
            } elseif (!empty($data['conditions_serialized'])) {
                $rule->setConditionsSerialized($data['conditions_serialized']);
            }

            $this->ruleRepository->save($rule);

            $savedRuleId = (int) $rule->getId();
            $this->saveRuleWebsites($savedRuleId, $data['website_ids'] ?? []);
            $this->saveRuleCustomerGroups($savedRuleId, $data['customer_group_ids'] ?? []);

            $this->messageManager->addSuccessMessage(__('The behavior earning rule has been saved.'));

            if ($this->getRequest()->getParam('back') === 'edit') {
                return $resultRedirect->setPath('*/*/edit', ['rule_id' => $savedRuleId]);
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the rule.'));
        }

        return $resultRedirect->setPath('*/*/edit', ['rule_id' => $data['rule_id'] ?? null]);
    }

    /**
     * Validate POST data
     *
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    private function validatePostData(array $data): void
    {
        if (empty($data['name'])) {
            throw new LocalizedException(__('Rule name is required.'));
        }

        $eventCode = $data['event_code'] ?? '';

        if (empty($eventCode)) {
            throw new LocalizedException(__('Event code is required.'));
        }

        $validCodes = BehaviorEvent::getValidEventCodes();

        if (!in_array($eventCode, $validCodes, true) && !preg_match('/^custom_/', $eventCode)) {
            throw new LocalizedException(
                __('Invalid event code "%1". Must be a valid code or start with "custom_".', $eventCode),
            );
        }

        if ((int) ($data['points'] ?? 0) <= 0) {
            throw new LocalizedException(__('Points must be greater than 0.'));
        }

        foreach (['expire_after_days', 'cap_per_day', 'cap_per_month', 'cap_per_year', 'cap_lifetime'] as $field) {
            if (isset($data[$field]) && (int) $data[$field] < 0) {
                throw new LocalizedException(__('"%1" must be 0 or greater.', $field));
            }
        }
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
            'rule_type = ?' => 'behavior_earning',
        ]);

        $rows = [];

        foreach ($websiteIds as $websiteId) {
            $rows[] = [
                'rule_id' => $ruleId,
                'rule_type' => 'behavior_earning',
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
            'rule_type = ?' => 'behavior_earning',
        ]);

        $rows = [];

        foreach ($groupIds as $groupId) {
            $rows[] = [
                'rule_id' => $ruleId,
                'rule_type' => 'behavior_earning',
                'customer_group_id' => (int) $groupId,
            ];
        }

        if (!empty($rows)) {
            $connection->insertMultiple($table, $rows);
        }
    }
}

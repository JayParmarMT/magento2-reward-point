<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\CatalogRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Meetanshi\RewardPoints\Api\CatalogRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\Rule\CatalogRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\CatalogRuleFactory;

/**
 * Save Catalog Rule controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::catalog_rule';

    private const VALID_ACTION_TYPES = ['fixed', 'per_price', 'per_profit'];

    /**
     * @param Context $context
     * @param CatalogRuleRepositoryInterface $ruleRepository
     * @param CatalogRuleFactory $ruleFactory
     * @param CatalogRuleConditionFactory $conditionFactory
     * @param ResourceConnection $resourceConnection
     * @param Json $json
     */
    public function __construct(
        Context $context,
        private readonly CatalogRuleRepositoryInterface $ruleRepository,
        private readonly CatalogRuleFactory $ruleFactory,
        private readonly CatalogRuleConditionFactory $conditionFactory,
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
            $rule->setFromDate(!empty($data['from_date']) ? $data['from_date'] : null);
            $rule->setToDate(!empty($data['to_date']) ? $data['to_date'] : null);
            $rule->setPriority((int) ($data['priority'] ?? 0));
            $rule->setActionType((string) $data['action_type']);
            $rule->setPoints((int) $data['points']);
            $rule->setMoneyStep(isset($data['money_step']) ? (float) $data['money_step'] : null);
            $rule->setMaxPoints((int) ($data['max_points'] ?? 0));
            $rule->setStopRulesProcessing((bool) ($data['stop_rules_processing'] ?? 0));

            // Convert the flat POST conditions array ({"1":{...},"1--1":{...}}) into the nested
            // format that AbstractModel::loadArray() understands ({"type":...,"conditions":[...]}).
            // Use CatalogRuleCondition (extends Magento\Rule\Model\AbstractModel) which has
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

            $this->messageManager->addSuccessMessage(__('The catalog earning rule has been saved.'));

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

        if (!empty($data['from_date']) && !empty($data['to_date'])) {
            if (strtotime($data['to_date']) < strtotime($data['from_date'])) {
                throw new LocalizedException(__('End Date must be greater than or equal to Start Date.'));
            }
        }

        if (!in_array($data['action_type'] ?? '', self::VALID_ACTION_TYPES, true)) {
            throw new LocalizedException(__('Invalid action type.'));
        }

        if ((int) ($data['points'] ?? 0) <= 0) {
            throw new LocalizedException(__('Points must be greater than 0.'));
        }

        if (($data['action_type'] ?? '') !== 'fixed') {
            if ((float) ($data['money_step'] ?? 0) <= 0) {
                throw new LocalizedException(__('Money Step must be greater than 0 for this action type.'));
            }
        }

        if (empty($data['website_ids'])) {
            throw new LocalizedException(__('Please select at least one website.'));
        }

        if (empty($data['customer_group_ids'])) {
            throw new LocalizedException(__('Please select at least one customer group.'));
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
            'rule_type = ?' => 'catalog_earning',
        ]);

        $rows = [];

        foreach ($websiteIds as $websiteId) {
            $rows[] = [
                'rule_id' => $ruleId,
                'rule_type' => 'catalog_earning',
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
            'rule_type = ?' => 'catalog_earning',
        ]);

        $rows = [];

        foreach ($groupIds as $groupId) {
            $rows[] = [
                'rule_id' => $ruleId,
                'rule_type' => 'catalog_earning',
                'customer_group_id' => (int) $groupId,
            ];
        }

        if (!empty($rows)) {
            $connection->insertMultiple($table, $rows);
        }
    }
}

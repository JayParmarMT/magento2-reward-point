<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\SpendingRule;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Serialize\Serializer\Json;
use Meetanshi\RewardPoints\Api\SpendingRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\Rule\SpendingRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\SpendingRuleFactory;
use Meetanshi\RewardPoints\Model\Source\DiscountAction;
use Meetanshi\RewardPoints\Model\Source\SpendingAction;
use Meetanshi\RewardPoints\Model\Source\SpendingStyle;

/**
 * Admin Spending Rule Save Controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::spending_rule';

    /**
     * @param Context $context
     * @param SpendingRuleRepositoryInterface $spendingRuleRepository
     * @param SpendingRuleFactory $spendingRuleFactory
     * @param SpendingRuleConditionFactory $conditionFactory
     * @param Json $json
     */
    public function __construct(
        Context $context,
        private readonly SpendingRuleRepositoryInterface $spendingRuleRepository,
        private readonly SpendingRuleFactory $spendingRuleFactory,
        private readonly SpendingRuleConditionFactory $conditionFactory,
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

        if (!$post) {
            return $resultRedirect->setPath('*/*/');
        }

        // Magento UI forms with root dataScope="data" nest all field values under $_POST['data'].
        // Fall back to the raw POST array for robustness (e.g. custom AJAX submissions).
        $data = (isset($post['data']) && is_array($post['data'])) ? $post['data'] : $post;

        // Conditions/actions are posted via the rule conditions block using data-form-part,
        // which bypasses the dataScope wrapper and arrives at root $_POST['rule'][...].
        // Merge them into $data['rule'] so the conditions serialization logic finds them.
        if (isset($post['rule']) && is_array($post['rule'])) {
            $data['rule'] = array_merge($data['rule'] ?? [], $post['rule']);
        }

        try {
            $this->validateData($data);

            $ruleId = isset($data['rule_id']) ? (int) $data['rule_id'] : null;

            if ($ruleId) {
                $rule = $this->spendingRuleRepository->getById($ruleId);
            } else {
                $rule = $this->spendingRuleFactory->create();
            }

            $rule->setName((string) $data['name']);
            $rule->setDescription($data['description'] ?? null);
            $rule->setIsActive((bool) ($data['is_active'] ?? false));
            $rule->setFromDate($data['from_date'] ?: null);
            $rule->setToDate($data['to_date'] ?: null);
            $rule->setPriority((int) ($data['priority'] ?? 0));
            $rule->setSpendingStyle((string) $data['spending_style']);
            $rule->setSpendingAction((string) $data['spending_action']);
            $rule->setPointsStep((int) $data['points_step']);
            $rule->setDiscountAction((string) $data['discount_action']);
            $rule->setDiscountAmount((float) ($data['discount_amount'] ?? 0));
            $rule->setData('min_points', (int) ($data['min_points'] ?? 0));
            $rule->setData('max_points', (int) ($data['max_points'] ?? 0));
            $rule->setData('apply_to_shipping', (int) ($data['apply_to_shipping'] ?? 0));
            $rule->setStopRulesProcessing((bool) ($data['stop_rules_processing'] ?? false));

            // Convert the flat POST conditions/actions array ({"1":{...},"1--1":{...}}) into the
            // nested format that AbstractModel::loadArray() understands ({"type":...,"conditions":[...]}).
            // Use SpendingRuleCondition (extends Magento\Rule\Model\AbstractModel) which has
            // loadPost() + _convertFlatToRecursive() built in, then read back via asArray().
            $conditionModel = $this->conditionFactory->create();

            if (!empty($data['rule']['conditions']) && is_array($data['rule']['conditions'])) {
                $conditionModel->loadPost(['conditions' => $data['rule']['conditions']]);
                $rule->setConditionsSerialized(
                    $this->json->serialize($conditionModel->getConditions()->asArray()),
                );
            } elseif (!empty($data['conditions_serialized'])) {
                $rule->setConditionsSerialized((string) $data['conditions_serialized']);
            }

            if (!empty($data['rule']['actions']) && is_array($data['rule']['actions'])) {
                $conditionModel->loadPost(['actions' => $data['rule']['actions']]);
                $rule->setActionsSerialized(
                    $this->json->serialize($conditionModel->getActions()->asArray()),
                );
            } elseif (!empty($data['actions_serialized'])) {
                $rule->setActionsSerialized((string) $data['actions_serialized']);
            }

            // Handle website_ids junction
            if (!empty($data['website_ids'])) {
                $rule->setData('website_ids', $data['website_ids']);
            }

            // Handle customer_group_ids junction
            if (!empty($data['customer_group_ids'])) {
                $rule->setData('customer_group_ids', $data['customer_group_ids']);
            }

            $this->spendingRuleRepository->save($rule);

            $this->messageManager->addSuccessMessage(__('The spending rule has been saved.'));

            if ($this->getRequest()->getParam('back')) {
                return $resultRedirect->setPath(
                    '*/*/edit',
                    ['rule_id' => $rule->getRuleId(), '_current' => true],
                );
            }

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $resultRedirect->setPath(
                '*/*/edit',
                ['rule_id' => $data['rule_id'] ?? null, '_current' => true],
            );
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the spending rule.'));

            return $resultRedirect->setPath(
                '*/*/edit',
                ['rule_id' => $data['rule_id'] ?? null, '_current' => true],
            );
        }
    }

    /**
     * Validate form data
     *
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    private function validateData(array $data): void
    {
        if (empty($data['name'])) {
            throw new LocalizedException(__('Rule name is required.'));
        }

        $validSpendingStyles = [SpendingStyle::FLEXIBLE, SpendingStyle::FIXED];

        if (!in_array($data['spending_style'] ?? '', $validSpendingStyles, true)) {
            throw new LocalizedException(
                __('Spending style must be one of: %1.', implode(', ', $validSpendingStyles)),
            );
        }

        $validSpendingActions = [SpendingAction::FIXED_POINTS, SpendingAction::PER_POINTS];

        if (!in_array($data['spending_action'] ?? '', $validSpendingActions, true)) {
            throw new LocalizedException(
                __('Spending action must be one of: %1.', implode(', ', $validSpendingActions)),
            );
        }

        if ((int) ($data['points_step'] ?? 0) <= 0) {
            throw new LocalizedException(__('Points step must be greater than zero.'));
        }

        $validDiscountActions = [DiscountAction::BY_FIXED, DiscountAction::BY_PERCENT, DiscountAction::FREE_SHIPPING];

        if (!in_array($data['discount_action'] ?? '', $validDiscountActions, true)) {
            throw new LocalizedException(
                __('Discount action must be one of: %1.', implode(', ', $validDiscountActions)),
            );
        }

        $discountAction = $data['discount_action'];
        $discountAmount = (float) ($data['discount_amount'] ?? 0);

        if ($discountAction !== DiscountAction::FREE_SHIPPING && $discountAmount <= 0) {
            throw new LocalizedException(__('Discount amount must be greater than zero.'));
        }

        if ($discountAction === DiscountAction::BY_PERCENT && ($discountAmount <= 0 || $discountAmount > 100)) {
            throw new LocalizedException(__('Percentage discount must be between 0 and 100.'));
        }

        if (empty($data['website_ids'])) {
            throw new LocalizedException(__('Please select at least one website.'));
        }

        if (empty($data['customer_group_ids'])) {
            throw new LocalizedException(__('Please select at least one customer group.'));
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

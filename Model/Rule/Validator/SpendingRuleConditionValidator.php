<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Validator;

use Magento\Framework\App\ResourceConnection;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\CollectionFactory as SpendingRuleCollectionFactory;
use Meetanshi\RewardPoints\Model\Rule\SpendingRule;
use Meetanshi\RewardPoints\Model\Rule\SpendingRuleConditionFactory;
use Psr\Log\LoggerInterface;

/**
 * Validates spending rule conditions against a quote at runtime.
 *
 * SpendingRule stores `conditions_serialized` in the same format as CartRule.
 * SpendingRuleCondition (extends AbstractModel) owns the deserialized tree and
 * uses CartCombine as the combine class — so validation uses a Quote\Address
 * exactly like cart earning rules.
 *
 * A spending discount is only applied when at least one active spending rule's
 * conditions match the current cart.  If no spending rules are defined at all,
 * the discount is unconditionally allowed (backward-compatible behavior).
 *
 * Usage:
 *   if (!$this->spendingRuleConditionValidator->hasMatchingRule($quote)) {
 *       // block redemption
 *   }
 */
class SpendingRuleConditionValidator
{
    private const RULE_TYPE = 'spending';

    /**
     * @param SpendingRuleCollectionFactory $collectionFactory
     * @param SpendingRuleConditionFactory $spendingRuleConditionFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly SpendingRuleCollectionFactory $collectionFactory,
        private readonly SpendingRuleConditionFactory $spendingRuleConditionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Check whether at least one active spending rule's conditions match the quote.
     *
     * Returns true when:
     *  - No spending rules exist at all (no restriction configured → allow)
     *  - At least one active rule scoped to this website/customer group passes conditions
     *
     * Returns false when:
     *  - At least one spending rule exists but none match the quote's scope and conditions
     *
     * @param Quote $quote
     * @return bool
     */
    public function hasMatchingRule(Quote $quote): bool
    {
        try {
            /** @var \Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\Collection $collection */
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter(SpendingRule::IS_ACTIVE, 1);

            // Date range filter
            $today = date('Y-m-d');
            $collection->addFieldToFilter(
                SpendingRule::FROM_DATE,
                [['null' => true], ['lteq' => $today]],
            );
            $collection->addFieldToFilter(
                SpendingRule::TO_DATE,
                [['null' => true], ['gteq' => $today]],
            );

            $collection->setOrder(SpendingRule::PRIORITY, 'ASC');

            /** @var SpendingRule[] $rules */
            $rules = $collection->getItems();

            // No spending rules configured → no condition restriction
            if (empty($rules)) {
                return true;
            }

            $websiteId = (int) $quote->getStore()->getWebsiteId();
            $customerGroupId = (int) $quote->getCustomerGroupId();
            $address = $this->getValidationAddress($quote);

            if ($address === null) {
                return false;
            }

            foreach ($rules as $rule) {
                if (!$this->ruleMatchesScope((int) $rule->getId(), $websiteId, $customerGroupId)) {
                    continue;
                }

                if ($this->rulePassesConditions($rule, $address)) {
                    return true;
                }
            }

            return false;
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints SpendingRuleConditionValidator: error loading/validating rules',
                ['exception' => $e],
            );

            // On error, allow spending to proceed (fail open for spending)
            return true;
        }
    }

    /**
     * Check whether a rule applies to the given website and customer group.
     *
     * Empty junction rows mean "applies to all" — a rule with no website rows
     * applies to every website; a rule with no group rows applies to every group.
     *
     * @param int $ruleId
     * @param int $websiteId
     * @param int $customerGroupId
     * @return bool
     */
    private function ruleMatchesScope(int $ruleId, int $websiteId, int $customerGroupId): bool
    {
        $connection = $this->resourceConnection->getConnection();
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        $websiteRows = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['website_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', self::RULE_TYPE),
        );

        if (!empty($websiteRows) && !in_array($websiteId, array_map('intval', $websiteRows), true)) {
            return false;
        }

        $groupRows = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['customer_group_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', self::RULE_TYPE),
        );

        if (!empty($groupRows) && !in_array($customerGroupId, array_map('intval', $groupRows), true)) {
            return false;
        }

        return true;
    }

    /**
     * Check whether a SpendingRule's conditions match the quote address.
     *
     * @param SpendingRule $rule
     * @param Address $address
     * @return bool
     */
    private function rulePassesConditions(SpendingRule $rule, Address $address): bool
    {
        $conditionsSerialized = $rule->getConditionsSerialized();

        // No conditions = unconditionally active
        if (empty($conditionsSerialized)) {
            return true;
        }

        try {
            $conditionModel = $this->spendingRuleConditionFactory->create();
            $conditionModel->setId($rule->getId());
            $conditionModel->setConditionsSerialized($conditionsSerialized);

            return (bool) $conditionModel->getConditions()->validate($address);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints SpendingRuleConditionValidator: condition error for rule ' . $rule->getId(),
                ['exception' => $e],
            );

            return false;
        }
    }

    /**
     * Get the quote address to use for condition validation.
     *
     * @param Quote $quote
     * @return Address|null
     */
    private function getValidationAddress(Quote $quote): ?Address
    {
        $shipping = $quote->getShippingAddress();

        if ($shipping && $shipping->getId()) {
            return $shipping;
        }

        $billing = $quote->getBillingAddress();

        if ($billing && $billing->getId()) {
            return $billing;
        }

        return null;
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Validator;

use Magento\Sales\Model\Order;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleConditionFactory;
use Psr\Log\LoggerInterface;

/**
 * Validates referral rule conditions against the triggering order at runtime.
 *
 * Referral rules fire when a referred customer completes their first order.  The
 * conditions tree (stored in `conditions_serialized`) uses CartCombine and is
 * validated against the concrete Order model, which extends AbstractModel and
 * therefore satisfies Combine::validate()'s type hint.  Computed alias fields
 * (base_subtotal_with_discount, group_id) are set via setData() before validation.
 *
 * Usage:
 *   if (!$this->referralRuleConditionValidator->ruleMatchesOrder($ruleId, $conditionsSerialized, $order)) {
 *       // skip this referral rule
 *   }
 */
class ReferralRuleConditionValidator
{
    /**
     * @param ReferralRuleConditionFactory $referralRuleConditionFactory
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ReferralRuleConditionFactory $referralRuleConditionFactory,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Check whether a referral rule's conditions match the given order.
     *
     * @param int $ruleId
     * @param string|null $conditionsSerialized
     * @param Order $order
     * @return bool  Returns true when conditions are empty (no restriction)
     */
    public function ruleMatchesOrder(int $ruleId, ?string $conditionsSerialized, Order $order): bool
    {
        // No conditions = unconditionally active
        if (empty($conditionsSerialized)) {
            return true;
        }

        try {
            $this->populateOrderAliases($order);

            $conditionModel = $this->referralRuleConditionFactory->create();
            $conditionModel->setId($ruleId);
            $conditionModel->setConditionsSerialized($conditionsSerialized);

            return (bool) $conditionModel->getConditions()->validate($order);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints ReferralRuleConditionValidator: condition error for rule ' . $ruleId,
                ['exception' => $e],
            );

            // On error, allow awarding to proceed
            return true;
        }
    }

    /**
     * Populate computed alias fields onto the Order model before condition validation.
     *
     * Native order fields (base_subtotal, subtotal, weight, shipping_method, etc.)
     * are already present on the Order model.  We add only the fields that are
     * computed or named differently:
     *   - base_subtotal_with_discount  (derived)
     *   - group_id                     (alias for customer_group_id)
     *
     * @param Order $order
     * @return void
     */
    private function populateOrderAliases(Order $order): void
    {
        $order->setData(
            'base_subtotal_with_discount',
            (float) $order->getBaseSubtotal() - (float) $order->getBaseDiscountAmount(),
        );
        $order->setData('group_id', (int) $order->getCustomerGroupId());
    }
}

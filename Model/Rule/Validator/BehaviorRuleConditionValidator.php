<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Validator;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleConditionFactory;
use Psr\Log\LoggerInterface;

/**
 * Validates behavior rule conditions against a customer at runtime.
 *
 * Behavior rules are triggered by customer events (signup, birthday, inactivity,
 * tier_up, tier_down, etc.) where there is no active cart/quote.  The loaded
 * Customer model (which extends AbstractModel) is used directly as the validation
 * model so that Combine::validate()'s AbstractModel type hint is satisfied.
 *
 * Extended reward-point attributes (lifetime_sales, number_of_orders, etc.) are
 * added to the customer model via setData() before validation so that custom
 * condition attributes can be evaluated against them.
 *
 * Cart-attribute conditions (subtotal, shipping_method, etc.) placed on a behavior
 * rule will evaluate as false because the customer model has no cart data — this is
 * the expected and documented behavior.  Only customer-attribute conditions make
 * semantic sense on behavior rules.
 *
 * Usage:
 *   if (!$this->behaviorRuleConditionValidator->ruleMatchesCustomer($ruleId, $conditionsSerialized, $customerId)) {
 *       // skip this rule
 *   }
 */
class BehaviorRuleConditionValidator
{
    /**
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param BehaviorRuleConditionFactory $behaviorRuleConditionFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerResource $customerResource,
        private readonly BehaviorRuleConditionFactory $behaviorRuleConditionFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Check whether a behavior rule's conditions match a given customer.
     *
     * @param int $ruleId
     * @param string|null $conditionsSerialized
     * @param int $customerId
     * @return bool  Returns true when conditions are empty (no restriction)
     */
    public function ruleMatchesCustomer(int $ruleId, ?string $conditionsSerialized, int $customerId): bool
    {
        // No conditions = unconditionally active
        if (empty($conditionsSerialized)) {
            return true;
        }

        try {
            $customer = $this->customerFactory->create();
            $this->customerResource->load($customer, $customerId);

            if (!$customer->getId()) {
                return false;
            }

            // Augment the Customer model with extended reward-point attributes so that
            // any custom condition attributes can be evaluated.  The Customer model extends
            // AbstractModel, satisfying Combine::validate()'s type hint directly.
            $this->populateExtendedAttributes($customer);

            $conditionModel = $this->behaviorRuleConditionFactory->create();
            $conditionModel->setId($ruleId);
            $conditionModel->setConditionsSerialized($conditionsSerialized);

            return (bool) $conditionModel->getConditions()->validate($customer);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints BehaviorRuleConditionValidator: condition error for rule ' . $ruleId,
                ['exception' => $e],
            );

            // On error, allow awarding to proceed (behavior events are fire-and-forget)
            return true;
        }
    }

    /**
     * Populate extended reward-point attributes onto the Customer model.
     *
     * These fields may not be present after a plain resource load, so we normalise
     * them to safe defaults.  The Customer model already carries customer_id and
     * group_id natively; we add aliases and extended fields here so that custom
     * CartCombine conditions can evaluate them.
     *
     * @param Customer $customer
     * @return void
     */
    private function populateExtendedAttributes(Customer $customer): void
    {
        $customerId = (int) $customer->getId();

        // Aliases used by condition attribute code lookups
        $customer->setData('customer_id', $customerId);
        $customer->setData('group_id', (int) $customer->getGroupId());

        // Extended reward-point attributes — default to 0 when not yet populated
        if ($customer->getData('lifetime_sales') === null) {
            $customer->setData('lifetime_sales', 0);
        }

        if ($customer->getData('lifetime_spent_points') === null) {
            $customer->setData('lifetime_spent_points', 0);
        }

        if ($customer->getData('number_of_orders') === null) {
            $customer->setData('number_of_orders', 0);
        }

        if ($customer->getData('number_of_reviews') === null) {
            $customer->setData('number_of_reviews', 0);
        }

        if ($customer->getData('is_referee') === null) {
            $customer->setData('is_referee', 0);
        }

        if ($customer->getData('is_referral') === null) {
            $customer->setData('is_referral', 0);
        }
    }
}

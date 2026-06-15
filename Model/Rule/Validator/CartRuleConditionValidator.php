<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Validator;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Model\AbstractModel;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\AddressFactory as QuoteAddressFactory;
use Magento\Sales\Model\Order;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\CollectionFactory as CartRuleCollectionFactory;
use Meetanshi\RewardPoints\Model\Rule\CartRule;
use Meetanshi\RewardPoints\Model\Rule\CartRuleConditionFactory;
use Psr\Log\LoggerInterface;

/**
 * Validates cart earning rule conditions against a quote or order at runtime.
 *
 * The conditions tree stored in `conditions_serialized` uses CartCombine, which
 * extends Magento's SalesRule Combine and validates against an AbstractModel instance.
 * Quote validation uses Quote\Address (shipping or billing) as the model.
 *
 * Order validation builds a transient Quote\Address populated with order data.
 * This is necessary because SalesRule\Condition\Address::validate() calls
 * $model->getQuote()->isVirtual() when the model is not a Quote\Address instance.
 * Passing a Quote\Address directly avoids that branch entirely.
 *
 * Usage (from quote):
 *   $matchingRules = $this->cartRuleConditionValidator->getMatchingRulesForQuote($quote);
 *
 * Usage (from order — post-placement):
 *   $matchingRules = $this->cartRuleConditionValidator->getMatchingRulesForOrder($order);
 */
class CartRuleConditionValidator
{
    private const RULE_TYPE = 'cart_earning';

    /**
     * @param CartRuleCollectionFactory $collectionFactory
     * @param CartRuleConditionFactory $cartRuleConditionFactory
     * @param QuoteAddressFactory $quoteAddressFactory
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CartRuleCollectionFactory $collectionFactory,
        private readonly CartRuleConditionFactory $cartRuleConditionFactory,
        private readonly QuoteAddressFactory $quoteAddressFactory,
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Return active cart earning rules whose conditions match the given quote,
     * sorted by priority ASC.  Respects stop_rules_processing.
     *
     * @param Quote $quote
     * @return CartRule[]  Indexed by rule_id
     */
    public function getMatchingRulesForQuote(Quote $quote): array
    {
        $address = $this->getValidationAddress($quote);

        if ($address === null) {
            return [];
        }

        $websiteId = (int) $quote->getStore()->getWebsiteId();
        $customerGroupId = (int) $quote->getCustomerGroupId();

        return $this->loadAndMatchRules($address, $websiteId, $customerGroupId);
    }

    /**
     * Return active cart earning rules whose conditions match the given order.
     *
     * SalesRule\Condition\Address::validate() calls $model->getQuote()->isVirtual()
     * when the model is not a Quote\Address instance.  Passing an Order directly
     * therefore triggers a fatal error because Order has no getQuote() method.
     *
     * To avoid this we build a transient Quote\Address populated with the order's
     * address/cart data.  Quote\Address is an AbstractModel subclass so it satisfies
     * Combine::validate()'s type hint, and it is a Quote\Address instance so the
     * Address condition takes the early-return path and never calls getQuote().
     *
     * @param Order $order
     * @return CartRule[]  Indexed by rule_id
     */
    public function getMatchingRulesForOrder(Order $order): array
    {
        $address = $this->buildAddressFromOrder($order);
        $websiteId = (int) $order->getStore()->getWebsiteId();
        $customerGroupId = (int) $order->getCustomerGroupId();

        return $this->loadAndMatchRules($address, $websiteId, $customerGroupId);
    }

    /**
     * Load active rules in priority order and return those whose conditions pass.
     *
     * Filters by website and customer group using the junction tables before
     * evaluating conditions — a rule with no junction rows applies to all.
     *
     * @param AbstractModel $validationModel  Quote\Address or Order instance
     * @param int $websiteId
     * @param int $customerGroupId
     * @return CartRule[]
     */
    private function loadAndMatchRules(AbstractModel $validationModel, int $websiteId, int $customerGroupId): array
    {
        try {
            /** @var \Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\Collection $collection */
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter(CartRule::IS_ACTIVE, 1);

            // Date range filter — only rules within their active window
            $today = date('Y-m-d');
            $collection->addFieldToFilter(
                CartRule::FROM_DATE,
                [['null' => true], ['lteq' => $today]],
            );
            $collection->addFieldToFilter(
                CartRule::TO_DATE,
                [['null' => true], ['gteq' => $today]],
            );

            $collection->setOrder(CartRule::PRIORITY, 'ASC');

            /** @var CartRule[] $rules */
            $rules = $collection->getItems();

            if (empty($rules)) {
                return [];
            }

            $matching = [];

            foreach ($rules as $rule) {
                if (!$this->ruleMatchesScope((int) $rule->getId(), $websiteId, $customerGroupId)) {
                    continue;
                }

                if (!$this->rulePassesConditions($rule, $validationModel)) {
                    continue;
                }

                $matching[$rule->getId()] = $rule;

                if ($rule->isStopRulesProcessing()) {
                    break;
                }
            }

            return $matching;
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints CartRuleConditionValidator: error loading/validating rules',
                ['exception' => $e],
            );

            return [];
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
     * Check whether a single CartRule's conditions match the validation model.
     *
     * Loads `CartRuleCondition` (which extends AbstractModel and owns the
     * deserialized conditions tree), sets the serialized conditions from the
     * plain `CartRule` model, then delegates to getConditions()->validate().
     *
     * @param CartRule $rule
     * @param AbstractModel $validationModel
     * @return bool
     */
    private function rulePassesConditions(CartRule $rule, AbstractModel $validationModel): bool
    {
        $conditionsSerialized = $rule->getConditionsSerialized();

        // A rule with no conditions is unconditionally active.
        if (empty($conditionsSerialized)) {
            return true;
        }

        try {
            // CartRuleCondition extends AbstractModel, owns the conditions tree.
            $conditionModel = $this->cartRuleConditionFactory->create();
            $conditionModel->setId($rule->getId());
            $conditionModel->setConditionsSerialized($conditionsSerialized);

            // Loads and deserializes the conditions tree via AbstractModel::getConditions().
            $conditions = $conditionModel->getConditions();

            return (bool) $conditions->validate($validationModel);
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints CartRuleConditionValidator: condition validation error for rule ' . $rule->getId(),
                ['exception' => $e],
            );

            // On error, treat as non-matching to err on the side of caution.
            return false;
        }
    }

    /**
     * Resolve the address to use for condition validation from a quote.
     *
     * SalesRule-style conditions validate against a Quote\Address.  We prefer the
     * shipping address (has subtotal, weight, etc.), and fall back to billing.
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

    /**
     * Build a transient Quote\Address populated from an Order for condition validation.
     *
     * SalesRule\Condition\Address::validate() checks instanceof Quote\Address and
     * uses the address directly when it matches — avoiding the getQuote() call that
     * would fail on a plain Order object.
     *
     * All fields read by Address condition attributes are mapped:
     *   base_subtotal, base_subtotal_with_discount, base_subtotal_total_incl_tax,
     *   total_qty, weight, postcode, region, region_id, country_id,
     *   shipping_method, payment_method.
     *
     * @param Order $order
     * @return Address
     */
    private function buildAddressFromOrder(Order $order): Address
    {
        /** @var Address $address */
        $address = $this->quoteAddressFactory->create();

        $orderShipping = $order->getShippingAddress();
        $baseSubtotal  = (float) $order->getBaseSubtotal();
        $baseDiscount  = abs((float) $order->getBaseDiscountAmount());

        $address->setData('base_subtotal', $baseSubtotal);
        $address->setData('base_subtotal_with_discount', $baseSubtotal - $baseDiscount);
        $address->setData(
            'base_subtotal_total_incl_tax',
            (float) $order->getBaseSubtotal() + (float) $order->getBaseTaxAmount(),
        );
        $address->setData('total_qty', (float) $order->getTotalQtyOrdered());
        $address->setData('weight', (float) $order->getWeight());
        $address->setData('shipping_method', (string) $order->getShippingMethod());
        $address->setData('payment_method', (string) $order->getPayment()?->getMethod());

        // Shipping address geographic fields
        if ($orderShipping) {
            $address->setData('postcode', (string) $orderShipping->getPostcode());
            $address->setData('region', (string) $orderShipping->getRegion());
            $address->setData('region_id', (int) $orderShipping->getRegionId());
            $address->setData('country_id', (string) $orderShipping->getCountryId());
        }

        // Customer group alias used by some condition types
        $address->setData('group_id', (int) $order->getCustomerGroupId());

        return $address;
    }
}

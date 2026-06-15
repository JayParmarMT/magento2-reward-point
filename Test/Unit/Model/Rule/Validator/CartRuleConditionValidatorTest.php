<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Rule\Validator;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Quote\Model\Quote\AddressFactory as QuoteAddressFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Store\Model\Store;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\Collection as CartRuleCollection;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule\CollectionFactory as CartRuleCollectionFactory;
use Meetanshi\RewardPoints\Model\Rule\CartRule;
use Meetanshi\RewardPoints\Model\Rule\CartRuleCondition;
use Meetanshi\RewardPoints\Model\Rule\CartRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\Validator\CartRuleConditionValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for CartRuleConditionValidator
 */
#[AllowMockObjectsWithoutExpectations]
class CartRuleConditionValidatorTest extends TestCase
{
    /** @var CartRuleCollectionFactory&MockObject */
    private CartRuleCollectionFactory $collectionFactory;

    /** @var CartRuleConditionFactory&MockObject */
    private CartRuleConditionFactory $cartRuleConditionFactory;

    /** @var QuoteAddressFactory&MockObject */
    private QuoteAddressFactory $quoteAddressFactory;

    /** @var ResourceConnection&MockObject */
    private ResourceConnection $resourceConnection;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var CartRuleConditionValidator */
    private CartRuleConditionValidator $validator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CartRuleCollectionFactory::class);
        $this->cartRuleConditionFactory = $this->createMock(CartRuleConditionFactory::class);
        $this->quoteAddressFactory = $this->createMock(QuoteAddressFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Wire resourceConnection so ruleMatchesScope() returns "applies to all scopes"
        // (empty fetchCol = no restrictions = rule applies to every website/group)
        $this->wireResourceConnectionToAllowAllScopes();

        $this->validator = new CartRuleConditionValidator(
            $this->collectionFactory,
            $this->cartRuleConditionFactory,
            $this->quoteAddressFactory,
            $this->resourceConnection,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // getMatchingRulesForQuote tests
    // -------------------------------------------------------------------------

    #[Test]
    public function getMatchingRulesForQuoteReturnsEmptyWhenNoShippingOrBillingAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $shippingAddress = $this->createMock(Address::class);
        $billingAddress = $this->createMock(Address::class);

        $shippingAddress->method('getId')->willReturn(null);
        $billingAddress->method('getId')->willReturn(null);

        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteUsesShippingAddressWhenAvailable(): void
    {
        [$quote, $shippingAddress] = $this->buildQuoteWithShippingAddress();
        $collection = $this->buildEmptyCollection();

        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteFallsBackToBillingAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $shippingAddress = $this->createMock(Address::class);
        $billingAddress = $this->createMock(Address::class);

        $shippingAddress->method('getId')->willReturn(null);
        $billingAddress->method('getId')->willReturn(10);

        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getStore')->willReturn($this->buildStore());
        $quote->method('getCustomerGroupId')->willReturn(1);

        $collection = $this->buildEmptyCollection();
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteReturnsEmptyWhenNoActiveRules(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();
        $collection = $this->buildEmptyCollection();

        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteReturnsRuleWhenConditionsEmpty(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule = $this->buildCartRule(1, '');
        $collection = $this->buildCollectionWithRules([$rule]);

        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertArrayHasKey(1, $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteReturnsRuleWhenConditionsPass(): void
    {
        [$quote, $address] = $this->buildQuoteWithShippingAddress();

        $rule = $this->buildCartRule(1, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule]);

        $this->collectionFactory->method('create')->willReturn($collection);
        $this->setupConditionFactory(true);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertArrayHasKey(1, $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteExcludesRuleWhenConditionsFail(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule = $this->buildCartRule(1, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule]);

        $this->collectionFactory->method('create')->willReturn($collection);
        $this->setupConditionFactory(false);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteStopsProcessingWhenFlagIsSet(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule1 = $this->buildCartRule(1, '', stopProcessing: true);
        $rule2 = $this->buildCartRule(2, '');

        $collection = $this->buildCollectionWithRules([$rule1, $rule2]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->validator->getMatchingRulesForQuote($quote);

        // Only rule1 should match — rule2 never evaluated because stop_rules_processing is true on rule1
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey(2, $result);
    }

    #[Test]
    public function getMatchingRulesForQuoteReturnsEmptyOnCollectionException(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $this->collectionFactory->method('create')->willThrowException(new \RuntimeException('DB error'));
        $this->logger->expects($this->once())->method('error');

        $result = $this->validator->getMatchingRulesForQuote($quote);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // getMatchingRulesForOrder tests
    // -------------------------------------------------------------------------

    #[Test]
    public function getMatchingRulesForOrderReturnsEmptyWhenNoRules(): void
    {
        $order = $this->buildOrder();
        $collection = $this->buildEmptyCollection();

        $this->collectionFactory->method('create')->willReturn($collection);

        $result = $this->validator->getMatchingRulesForOrder($order);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getMatchingRulesForOrderReturnsRuleWhenConditionsPass(): void
    {
        $order = $this->buildOrder();
        $rule = $this->buildCartRule(5, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule]);

        $this->collectionFactory->method('create')->willReturn($collection);
        $this->setupConditionFactory(true);

        $result = $this->validator->getMatchingRulesForOrder($order);

        $this->assertArrayHasKey(5, $result);
    }

    #[Test]
    public function getMatchingRulesForOrderPopulatesAliasesAndPassesQuoteAddressToValidate(): void
    {
        // The validator builds a transient Quote\Address from the Order's data and
        // passes that to validate() — NOT the Order directly. This prevents a fatal
        // error from SalesRule\Condition\Address calling $model->getQuote() on Order.
        $order = $this->buildOrder(
            baseSubtotal: 100.0,
            subtotal: 100.0,
            baseDiscountAmount: 10.0,
            totalQty: 3.0,
            weight: 2.5,
            shippingMethod: 'flatrate_flatrate',
            customerId: 42,
            customerGroupId: 2,
        );

        $rule = $this->buildCartRule(1, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule]);
        $this->collectionFactory->method('create')->willReturn($collection);

        // Capture the model passed to validate()
        $capturedModel = null;
        $conditionModel = $this->createMock(CartRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturnCallback(
            function ($model) use (&$capturedModel) {
                $capturedModel = $model;

                return true;
            },
        );
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->cartRuleConditionFactory->method('create')->willReturn($conditionModel);

        $result = $this->validator->getMatchingRulesForOrder($order);

        $this->assertArrayHasKey(1, $result);

        // A Quote\Address — not the Order — must be passed to validate()
        // so that Address conditions can call $model->getQuote() safely.
        $this->assertInstanceOf(Address::class, $capturedModel);

        // Order data must be mapped onto the address before validation
        $this->assertSame(100.0, $capturedModel->getData('base_subtotal'));
        $this->assertSame(90.0, $capturedModel->getData('base_subtotal_with_discount'));
        $this->assertSame(3.0, $capturedModel->getData('total_qty'));
        $this->assertSame(2.5, $capturedModel->getData('weight'));
        $this->assertSame('flatrate_flatrate', $capturedModel->getData('shipping_method'));
    }

    #[Test]
    public function getMatchingRulesForOrderReturnsEmptyOnException(): void
    {
        $order = $this->buildOrder();

        $this->collectionFactory->method('create')->willThrowException(new \RuntimeException('DB error'));
        $this->logger->expects($this->once())->method('error');

        $result = $this->validator->getMatchingRulesForOrder($order);

        $this->assertSame([], $result);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * @return array{Quote&MockObject, Address&MockObject}
     */
    private function buildQuoteWithShippingAddress(): array
    {
        $quote = $this->createMock(Quote::class);
        $shippingAddress = $this->createMock(Address::class);

        $shippingAddress->method('getId')->willReturn(1);
        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getStore')->willReturn($this->buildStore());
        $quote->method('getCustomerGroupId')->willReturn(1);

        return [$quote, $shippingAddress];
    }

    /**
     * @return Store&MockObject
     */
    private function buildStore(int $websiteId = 1): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getWebsiteId')->willReturn($websiteId);

        return $store;
    }

    /**
     * Wire resourceConnection so ruleMatchesScope() passes for all rules.
     * Empty fetchCol() = no scope restrictions = rule applies to every website/group.
     */
    private function wireResourceConnectionToAllowAllScopes(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();

        $connection = $this->createMock(AdapterInterface::class);
        $connection->method('select')->willReturn($select);
        $connection->method('fetchCol')->willReturn([]);

        $this->resourceConnection->method('getConnection')->willReturn($connection);
        $this->resourceConnection->method('getTableName')->willReturnArgument(0);
    }

    /**
     * @return CartRuleCollection&MockObject
     */
    private function buildEmptyCollection(): CartRuleCollection
    {
        $collection = $this->createMock(CartRuleCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn([]);

        return $collection;
    }

    /**
     * @param CartRule[] $rules
     * @return CartRuleCollection&MockObject
     */
    private function buildCollectionWithRules(array $rules): CartRuleCollection
    {
        $collection = $this->createMock(CartRuleCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($rules);

        return $collection;
    }

    /**
     * @param int $id
     * @param string $conditionsSerialized
     * @param bool $stopProcessing
     * @return CartRule&MockObject
     */
    private function buildCartRule(int $id, string $conditionsSerialized, bool $stopProcessing = false): CartRule
    {
        $rule = $this->createMock(CartRule::class);
        $rule->method('getId')->willReturn($id);
        $rule->method('getConditionsSerialized')->willReturn($conditionsSerialized);
        $rule->method('isStopRulesProcessing')->willReturn($stopProcessing);

        return $rule;
    }

    /**
     * @param bool $validates
     * @return void
     */
    private function setupConditionFactory(bool $validates): void
    {
        $conditionModel = $this->createMock(CartRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturn($validates);
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->cartRuleConditionFactory->method('create')->willReturn($conditionModel);
    }

    /**
     * Build a partial Order mock where getData/setData use the real DataObject
     * implementation so that populateOrderAliases() values are readable in tests.
     *
     * @param float $baseSubtotal
     * @param float $subtotal
     * @param float $baseDiscountAmount
     * @param float $totalQty
     * @param float $weight
     * @param string $shippingMethod
     * @param int $customerId
     * @param int $customerGroupId
     * @return Order&MockObject
     */
    private function buildOrder(
        float $baseSubtotal = 50.0,
        float $subtotal = 50.0,
        float $baseDiscountAmount = 0.0,
        float $totalQty = 1.0,
        float $weight = 1.0,
        string $shippingMethod = 'flatrate_flatrate',
        int $customerId = 1,
        int $customerGroupId = 1,
    ): Order {
        // Use a real Address mock that supports setData/getData via DataObject
        $quoteAddress = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $this->quoteAddressFactory->method('create')->willReturn($quoteAddress);

        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getBaseSubtotal',
                'getBaseTaxAmount',
                'getSubtotal',
                'getBaseDiscountAmount',
                'getTotalQtyOrdered',
                'getWeight',
                'getShippingMethod',
                'getCustomerId',
                'getCustomerGroupId',
                'getPayment',
                'getShippingAddress',
                'getStore',
            ])
            ->getMock();

        $order->method('getBaseSubtotal')->willReturn($baseSubtotal);
        $order->method('getBaseTaxAmount')->willReturn(0.0);
        $order->method('getSubtotal')->willReturn($subtotal);
        $order->method('getBaseDiscountAmount')->willReturn($baseDiscountAmount);
        $order->method('getTotalQtyOrdered')->willReturn($totalQty);
        $order->method('getWeight')->willReturn($weight);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getCustomerGroupId')->willReturn($customerGroupId);
        $order->method('getPayment')->willReturn(null);
        $order->method('getShippingAddress')->willReturn(null);
        $order->method('getStore')->willReturn($this->buildStore());

        return $order;
    }
}

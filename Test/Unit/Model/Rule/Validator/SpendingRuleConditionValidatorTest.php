<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Rule\Validator;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\Store;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\Collection as SpendingRuleCollection;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\CollectionFactory as SpendingRuleCollectionFactory;
use Meetanshi\RewardPoints\Model\Rule\SpendingRule;
use Meetanshi\RewardPoints\Model\Rule\SpendingRuleCondition;
use Meetanshi\RewardPoints\Model\Rule\SpendingRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\Validator\SpendingRuleConditionValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for SpendingRuleConditionValidator
 */
#[AllowMockObjectsWithoutExpectations]
class SpendingRuleConditionValidatorTest extends TestCase
{
    /** @var SpendingRuleCollectionFactory&MockObject */
    private SpendingRuleCollectionFactory $collectionFactory;

    /** @var SpendingRuleConditionFactory&MockObject */
    private SpendingRuleConditionFactory $spendingRuleConditionFactory;

    /** @var ResourceConnection&MockObject */
    private ResourceConnection $resourceConnection;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var SpendingRuleConditionValidator */
    private SpendingRuleConditionValidator $validator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(SpendingRuleCollectionFactory::class);
        $this->spendingRuleConditionFactory = $this->createMock(SpendingRuleConditionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Wire resourceConnection so ruleMatchesScope() returns "applies to all scopes"
        $this->wireResourceConnectionToAllowAllScopes();

        $this->validator = new SpendingRuleConditionValidator(
            $this->collectionFactory,
            $this->spendingRuleConditionFactory,
            $this->resourceConnection,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Fail-open: no rules configured → always allow spending
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleReturnsTrueWhenNoSpendingRulesExist(): void
    {
        $quote = $this->createMock(Quote::class);
        $collection = $this->buildCollectionWithRules([]);

        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertTrue($this->validator->hasMatchingRule($quote));
    }

    // -------------------------------------------------------------------------
    // No address → false (rules exist but we cannot validate)
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleReturnsFalseWhenRulesExistButNoAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $shippingAddress = $this->createMock(Address::class);
        $billingAddress = $this->createMock(Address::class);

        $shippingAddress->method('getId')->willReturn(null);
        $billingAddress->method('getId')->willReturn(null);

        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getStore')->willReturn($this->buildStore());
        $quote->method('getCustomerGroupId')->willReturn(1);

        $rule = $this->buildSpendingRule(1, '');
        $collection = $this->buildCollectionWithRules([$rule]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertFalse($this->validator->hasMatchingRule($quote));
    }

    // -------------------------------------------------------------------------
    // Empty conditions on rule → unconditionally match
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleReturnsTrueWhenRuleHasNoConditions(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule = $this->buildSpendingRule(1, '');
        $collection = $this->buildCollectionWithRules([$rule]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertTrue($this->validator->hasMatchingRule($quote));
    }

    // -------------------------------------------------------------------------
    // Conditions pass → allow spending
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleReturnsTrueWhenOneRuleConditionsPass(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule = $this->buildSpendingRule(2, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule]);
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->setupConditionFactory(true);

        $this->assertTrue($this->validator->hasMatchingRule($quote));
    }

    #[Test]
    public function hasMatchingRuleReturnsTrueWhenSecondRuleConditionsPass(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule1 = $this->buildSpendingRule(1, '{"conditions": []}');
        $rule2 = $this->buildSpendingRule(2, '{"conditions": []}');

        // Set up factory to fail first call, pass second call
        $conditionModel1 = $this->createMock(SpendingRuleCondition::class);
        $combine1 = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine1->method('validate')->willReturn(false);
        $conditionModel1->method('getConditions')->willReturn($combine1);

        $conditionModel2 = $this->createMock(SpendingRuleCondition::class);
        $combine2 = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine2->method('validate')->willReturn(true);
        $conditionModel2->method('getConditions')->willReturn($combine2);

        $this->spendingRuleConditionFactory->method('create')
            ->willReturnOnConsecutiveCalls($conditionModel1, $conditionModel2);

        $collection = $this->buildCollectionWithRules([$rule1, $rule2]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertTrue($this->validator->hasMatchingRule($quote));
    }

    // -------------------------------------------------------------------------
    // All conditions fail → block spending
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleReturnsFalseWhenAllRulesConditionsFail(): void
    {
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule1 = $this->buildSpendingRule(1, '{"conditions": []}');
        $rule2 = $this->buildSpendingRule(2, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule1, $rule2]);
        $this->collectionFactory->method('create')->willReturn($collection);
        $this->setupConditionFactory(false);

        $this->assertFalse($this->validator->hasMatchingRule($quote));
    }

    // -------------------------------------------------------------------------
    // Exception handling — fail open for spending
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleReturnsTrueOnCollectionException(): void
    {
        $quote = $this->createMock(Quote::class);

        $this->collectionFactory->method('create')->willThrowException(new \RuntimeException('DB error'));
        $this->logger->expects($this->once())->method('error');

        $this->assertTrue($this->validator->hasMatchingRule($quote));
    }

    #[Test]
    public function hasMatchingRuleReturnsFalseWhenConditionValidationThrows(): void
    {
        // Condition validation throws → rulePassesConditions returns false
        // If all rules fail, hasMatchingRule returns false
        [$quote] = $this->buildQuoteWithShippingAddress();

        $rule = $this->buildSpendingRule(1, '{"conditions": []}');
        $collection = $this->buildCollectionWithRules([$rule]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $conditionModel = $this->createMock(SpendingRuleCondition::class);
        $conditionModel->method('getConditions')->willThrowException(new \RuntimeException('Condition error'));
        $this->spendingRuleConditionFactory->method('create')->willReturn($conditionModel);
        $this->logger->expects($this->once())->method('warning');

        $this->assertFalse($this->validator->hasMatchingRule($quote));
    }

    // -------------------------------------------------------------------------
    // Billing address fallback
    // -------------------------------------------------------------------------

    #[Test]
    public function hasMatchingRuleFallsBackToBillingAddress(): void
    {
        $quote = $this->createMock(Quote::class);
        $shippingAddress = $this->createMock(Address::class);
        $billingAddress = $this->createMock(Address::class);

        $shippingAddress->method('getId')->willReturn(null);
        $billingAddress->method('getId')->willReturn(5);

        $quote->method('getShippingAddress')->willReturn($shippingAddress);
        $quote->method('getBillingAddress')->willReturn($billingAddress);
        $quote->method('getStore')->willReturn($this->buildStore());
        $quote->method('getCustomerGroupId')->willReturn(1);

        $rule = $this->buildSpendingRule(1, '');
        $collection = $this->buildCollectionWithRules([$rule]);
        $this->collectionFactory->method('create')->willReturn($collection);

        $this->assertTrue($this->validator->hasMatchingRule($quote));
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
     * @param SpendingRule[] $rules
     * @return SpendingRuleCollection&MockObject
     */
    private function buildCollectionWithRules(array $rules): SpendingRuleCollection
    {
        $collection = $this->createMock(SpendingRuleCollection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('getItems')->willReturn($rules);

        return $collection;
    }

    /**
     * @param int $id
     * @param string $conditionsSerialized
     * @return SpendingRule&MockObject
     */
    private function buildSpendingRule(int $id, string $conditionsSerialized): SpendingRule
    {
        $rule = $this->createMock(SpendingRule::class);
        $rule->method('getId')->willReturn($id);
        $rule->method('getConditionsSerialized')->willReturn($conditionsSerialized);

        return $rule;
    }

    /**
     * @param bool $validates
     * @return void
     */
    private function setupConditionFactory(bool $validates): void
    {
        $conditionModel = $this->createMock(SpendingRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturn($validates);
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->spendingRuleConditionFactory->method('create')->willReturn($conditionModel);
    }
}

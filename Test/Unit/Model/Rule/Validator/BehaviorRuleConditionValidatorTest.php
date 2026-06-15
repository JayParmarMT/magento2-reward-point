<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Rule\Validator;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Framework\App\ResourceConnection;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleCondition;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\Validator\BehaviorRuleConditionValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for BehaviorRuleConditionValidator
 */
#[AllowMockObjectsWithoutExpectations]
class BehaviorRuleConditionValidatorTest extends TestCase
{
    /** @var CustomerFactory&MockObject */
    private CustomerFactory $customerFactory;

    /** @var CustomerResource&MockObject */
    private CustomerResource $customerResource;

    /** @var BehaviorRuleConditionFactory&MockObject */
    private BehaviorRuleConditionFactory $behaviorRuleConditionFactory;

    /** @var ResourceConnection&MockObject */
    private ResourceConnection $resourceConnection;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var BehaviorRuleConditionValidator */
    private BehaviorRuleConditionValidator $validator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->customerFactory = $this->createMock(CustomerFactory::class);
        $this->customerResource = $this->createMock(CustomerResource::class);
        $this->behaviorRuleConditionFactory = $this->createMock(BehaviorRuleConditionFactory::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->validator = new BehaviorRuleConditionValidator(
            $this->customerFactory,
            $this->customerResource,
            $this->behaviorRuleConditionFactory,
            $this->resourceConnection,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Empty / null conditions → unconditionally active
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesCustomerReturnsTrueWhenConditionsNull(): void
    {
        $this->assertTrue($this->validator->ruleMatchesCustomer(1, null, 42));
    }

    #[Test]
    public function ruleMatchesCustomerReturnsTrueWhenConditionsEmpty(): void
    {
        $this->assertTrue($this->validator->ruleMatchesCustomer(1, '', 42));
    }

    // -------------------------------------------------------------------------
    // Customer not found → false
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesCustomerReturnsFalseWhenCustomerNotFound(): void
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(null);

        $this->customerFactory->method('create')->willReturn($customer);
        $this->customerResource->method('load')->willReturnSelf();

        $result = $this->validator->ruleMatchesCustomer(1, '{"conditions": []}', 999);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Customer found, conditions pass
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesCustomerReturnsTrueWhenConditionsPass(): void
    {
        [$customer] = $this->buildCustomer(42, 1);
        $this->setupConditionFactory(true);

        $result = $this->validator->ruleMatchesCustomer(1, '{"conditions": []}', 42);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Customer found, conditions fail
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesCustomerReturnsFalseWhenConditionsFail(): void
    {
        [$customer] = $this->buildCustomer(42, 1);
        $this->setupConditionFactory(false);

        $result = $this->validator->ruleMatchesCustomer(1, '{"conditions": []}', 42);

        $this->assertFalse($result);
    }

    // -------------------------------------------------------------------------
    // Extended attributes populated on Customer model
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesCustomerPopulatesExtendedAttributesOnCustomer(): void
    {
        // Use a real Customer-like object that records setData calls so we can
        // verify the validator augments it with the correct attribute keys.
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn(7);
        $customer->method('getGroupId')->willReturn(3);
        // Extended attributes already present — should not be overwritten with 0
        $customer->method('getData')->willReturnMap([
            ['lifetime_sales', null, 250.0],
            ['lifetime_spent_points', null, 100],
            ['number_of_orders', null, 5],
            ['number_of_reviews', null, 2],
            ['is_referee', null, 1],
            ['is_referral', null, 0],
        ]);

        // setData must be called for alias keys (customer_id, group_id)
        $customer->expects($this->atLeastOnce())
            ->method('setData')
            ->with($this->logicalOr(
                $this->equalTo('customer_id'),
                $this->equalTo('group_id'),
            ));

        $this->customerFactory->method('create')->willReturn($customer);
        $this->customerResource->method('load')->willReturnSelf();

        // Capture what is passed to validate() — must be the Customer instance
        $capturedModel = null;
        $conditionModel = $this->createMock(BehaviorRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturnCallback(
            function ($model) use (&$capturedModel) {
                $capturedModel = $model;

                return true;
            },
        );
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->behaviorRuleConditionFactory->method('create')->willReturn($conditionModel);

        $this->validator->ruleMatchesCustomer(1, '{"conditions": []}', 7);

        // The Customer model (AbstractModel subclass) must be passed, not a plain DataObject
        $this->assertInstanceOf(Customer::class, $capturedModel);
        $this->assertSame($customer, $capturedModel);
    }

    // -------------------------------------------------------------------------
    // Exception handling — fail open (behavior events are fire-and-forget)
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesCustomerReturnsTrueOnCustomerLoadException(): void
    {
        $this->customerFactory->method('create')->willThrowException(new \RuntimeException('Load error'));
        $this->logger->expects($this->once())->method('warning');

        $result = $this->validator->ruleMatchesCustomer(1, '{"conditions": []}', 42);

        $this->assertTrue($result);
    }

    #[Test]
    public function ruleMatchesCustomerReturnsTrueOnConditionValidationException(): void
    {
        [$customer] = $this->buildCustomer(42, 1);

        $conditionModel = $this->createMock(BehaviorRuleCondition::class);
        $conditionModel->method('getConditions')->willThrowException(new \RuntimeException('Condition error'));
        $this->behaviorRuleConditionFactory->method('create')->willReturn($conditionModel);
        $this->logger->expects($this->once())->method('warning');

        $result = $this->validator->ruleMatchesCustomer(1, '{"conditions": []}', 42);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * @param int $customerId
     * @param int $groupId
     * @return array{Customer&MockObject}
     */
    private function buildCustomer(int $customerId, int $groupId): array
    {
        $customer = $this->createMock(Customer::class);
        $customer->method('getId')->willReturn($customerId);
        $customer->method('getGroupId')->willReturn($groupId);
        $customer->method('getData')->willReturn(null);

        $this->customerFactory->method('create')->willReturn($customer);
        $this->customerResource->method('load')->willReturnSelf();

        return [$customer];
    }

    /**
     * @param bool $validates
     * @return void
     */
    private function setupConditionFactory(bool $validates): void
    {
        $conditionModel = $this->createMock(BehaviorRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturn($validates);
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->behaviorRuleConditionFactory->method('create')->willReturn($conditionModel);
    }
}

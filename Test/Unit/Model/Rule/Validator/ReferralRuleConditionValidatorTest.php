<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Rule\Validator;

use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleCondition;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleConditionFactory;
use Meetanshi\RewardPoints\Model\Rule\Validator\ReferralRuleConditionValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ReferralRuleConditionValidator
 */
#[AllowMockObjectsWithoutExpectations]
class ReferralRuleConditionValidatorTest extends TestCase
{
    /** @var ReferralRuleConditionFactory&MockObject */
    private ReferralRuleConditionFactory $referralRuleConditionFactory;

    /** @var LoggerInterface&MockObject */
    private LoggerInterface $logger;

    /** @var ReferralRuleConditionValidator */
    private ReferralRuleConditionValidator $validator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->referralRuleConditionFactory = $this->createMock(ReferralRuleConditionFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->validator = new ReferralRuleConditionValidator(
            $this->referralRuleConditionFactory,
            $this->logger,
        );
    }

    // -------------------------------------------------------------------------
    // Empty / null conditions → unconditionally active
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesOrderReturnsTrueWhenConditionsNull(): void
    {
        $order = $this->createMock(Order::class);

        $this->assertTrue($this->validator->ruleMatchesOrder(1, null, $order));
    }

    #[Test]
    public function ruleMatchesOrderReturnsTrueWhenConditionsEmpty(): void
    {
        $order = $this->createMock(Order::class);

        $this->assertTrue($this->validator->ruleMatchesOrder(1, '', $order));
    }

    // -------------------------------------------------------------------------
    // Conditions pass → award referral
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesOrderReturnsTrueWhenConditionsPass(): void
    {
        $order = $this->buildOrder();
        $this->setupConditionFactory(true);

        $this->assertTrue($this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order));
    }

    // -------------------------------------------------------------------------
    // Conditions fail → skip referral
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesOrderReturnsFalseWhenConditionsFail(): void
    {
        $order = $this->buildOrder();
        $this->setupConditionFactory(false);

        $this->assertFalse($this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order));
    }

    // -------------------------------------------------------------------------
    // Proxy structure — order fields mapped correctly
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesOrderPopulatesAliasesAndPassesOrderToValidate(): void
    {
        $order = $this->buildOrder(
            baseSubtotal: 200.0,
            subtotal: 200.0,
            baseDiscountAmount: 20.0,
            totalQty: 4.0,
            weight: 3.0,
            shippingMethod: 'ups_ground',
            customerId: 10,
            customerGroupId: 3,
        );

        $capturedModel = null;
        $conditionModel = $this->createMock(ReferralRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturnCallback(
            function ($model) use (&$capturedModel) {
                $capturedModel = $model;

                return true;
            },
        );
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->referralRuleConditionFactory->method('create')->willReturn($conditionModel);

        $this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order);

        // The Order model itself must be passed — not a DataObject proxy
        $this->assertInstanceOf(Order::class, $capturedModel);
        $this->assertSame($order, $capturedModel);
        // Alias fields set by populateOrderAliases()
        $this->assertSame(180.0, $capturedModel->getData('base_subtotal_with_discount'));
        $this->assertSame(3, $capturedModel->getData('group_id'));
    }

    #[Test]
    public function ruleMatchesOrderHandlesNullShippingAddress(): void
    {
        $order = $this->buildOrder(shippingAddress: null);
        $this->setupConditionFactory(true);

        $result = $this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order);

        $this->assertTrue($result);
    }

    #[Test]
    public function ruleMatchesOrderHandlesNullPayment(): void
    {
        // Order mock returns null for getPayment() — validator must not throw
        $order = $this->buildOrder();
        $this->setupConditionFactory(true);

        $result = $this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // Exception handling — fail open (allow awarding on errors)
    // -------------------------------------------------------------------------

    #[Test]
    public function ruleMatchesOrderReturnsTrueOnConditionException(): void
    {
        $order = $this->buildOrder();

        $conditionModel = $this->createMock(ReferralRuleCondition::class);
        $conditionModel->method('getConditions')->willThrowException(new \RuntimeException('Condition error'));
        $this->referralRuleConditionFactory->method('create')->willReturn($conditionModel);
        $this->logger->expects($this->once())->method('warning');

        $result = $this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order);

        $this->assertTrue($result);
    }

    #[Test]
    public function ruleMatchesOrderReturnsTrueOnFactoryException(): void
    {
        $order = $this->buildOrder();

        $this->referralRuleConditionFactory->method('create')
            ->willThrowException(new \RuntimeException('Factory error'));
        $this->logger->expects($this->once())->method('warning');

        $result = $this->validator->ruleMatchesOrder(1, '{"conditions": []}', $order);

        $this->assertTrue($result);
    }

    #[Test]
    public function ruleMatchesOrderLogsWarningWithCorrectRuleId(): void
    {
        $order = $this->buildOrder();
        $ruleId = 99;

        $this->referralRuleConditionFactory->method('create')
            ->willThrowException(new \RuntimeException('error'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                $this->stringContains((string) $ruleId),
                $this->anything(),
            );

        $this->validator->ruleMatchesOrder($ruleId, '{"conditions": []}', $order);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * @param float $baseSubtotal
     * @param float $subtotal
     * @param float $baseDiscountAmount
     * @param float $totalQty
     * @param float $weight
     * @param string $shippingMethod
     * @param int $customerId
     * @param int $customerGroupId
     * @param OrderAddress|null $shippingAddress
     * @return Order&MockObject
     */
    private function buildOrder(
        float $baseSubtotal = 100.0,
        float $subtotal = 100.0,
        float $baseDiscountAmount = 0.0,
        float $totalQty = 2.0,
        float $weight = 1.5,
        string $shippingMethod = 'flatrate_flatrate',
        int $customerId = 1,
        int $customerGroupId = 1,
        ?OrderAddress $shippingAddress = null,
    ): Order {
        // Partial mock: only stub named methods; getData/setData use real DataObject
        // so that populateOrderAliases() values are readable in assertions.
        $order = $this->getMockBuilder(Order::class)
            ->disableOriginalConstructor()
            ->onlyMethods([
                'getBaseSubtotal',
                'getSubtotal',
                'getBaseDiscountAmount',
                'getTotalQtyOrdered',
                'getWeight',
                'getShippingMethod',
                'getCustomerId',
                'getCustomerGroupId',
                'getPayment',
                'getShippingAddress',
            ])
            ->getMock();

        $order->method('getBaseSubtotal')->willReturn($baseSubtotal);
        $order->method('getSubtotal')->willReturn($subtotal);
        $order->method('getBaseDiscountAmount')->willReturn($baseDiscountAmount);
        $order->method('getTotalQtyOrdered')->willReturn($totalQty);
        $order->method('getWeight')->willReturn($weight);
        $order->method('getShippingMethod')->willReturn($shippingMethod);
        $order->method('getCustomerId')->willReturn($customerId);
        $order->method('getCustomerGroupId')->willReturn($customerGroupId);
        $order->method('getPayment')->willReturn(null);
        $order->method('getShippingAddress')->willReturn($shippingAddress);

        return $order;
    }

    /**
     * @param bool $validates
     * @return void
     */
    private function setupConditionFactory(bool $validates): void
    {
        $conditionModel = $this->createMock(ReferralRuleCondition::class);
        $combine = $this->createMock(\Magento\Rule\Model\Condition\Combine::class);
        $combine->method('validate')->willReturn($validates);
        $conditionModel->method('getConditions')->willReturn($combine);
        $this->referralRuleConditionFactory->method('create')->willReturn($conditionModel);
    }
}

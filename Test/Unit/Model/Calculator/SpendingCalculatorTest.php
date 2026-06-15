<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Calculator;

use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Model\Calculator\SpendingCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Unit tests for SpendingCalculator
 */
#[AllowMockObjectsWithoutExpectations]
class SpendingCalculatorTest extends TestCase
{
    private SpendingCalculator $calculator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->calculator = new SpendingCalculator();
    }

    // -------------------------------------------------------------------------
    // calculateDiscountForPoints
    // -------------------------------------------------------------------------

    #[Test]
    public function calculateDiscountForPointsConvertsPointsToDiscountAmount(): void
    {
        // Rate: every 100 pts → $5.00. 300 pts = 3 steps × $5.00 = $15.00.
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculateDiscountForPoints(300, $rate);

        $this->assertSame(15.0, $result);
    }

    #[Test]
    public function calculateDiscountForPointsReturnsZeroWhenPointsBelowMinimum(): void
    {
        // Minimum 200 pts required; customer has only 100.
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 200);

        $result = $this->calculator->calculateDiscountForPoints(100, $rate);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function calculateDiscountForPointsReturnsZeroWhenZeroPoints(): void
    {
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculateDiscountForPoints(0, $rate);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function calculateDiscountForPointsReturnsZeroWhenPointsPerStepIsZero(): void
    {
        $rate = $this->buildRate(points: 0, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculateDiscountForPoints(100, $rate);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function calculateDiscountForPointsReturnsZeroWhenCurrencyAmountIsZero(): void
    {
        $rate = $this->buildRate(points: 100, currencyAmount: 0.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculateDiscountForPoints(100, $rate);

        $this->assertSame(0.0, $result);
    }

    #[Test]
    public function calculateDiscountForPointsFloorsPartialSteps(): void
    {
        // Every 100 pts → $5. 250 pts = floor(250/100)=2 steps × $5 = $10 (not $12.50).
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculateDiscountForPoints(250, $rate);

        $this->assertSame(10.0, $result);
    }

    #[Test]
    public function calculateDiscountForPointsRoundsToFourDecimalPlaces(): void
    {
        // Every 3 pts → $1.0. 1 step × $1.0 = $1.0000 (no rounding issue here).
        // Use a rate where currency amount has many decimals to trigger rounding.
        $rate = $this->buildRate(points: 3, currencyAmount: 1.0001, minPointsPerOrder: 0);

        $result = $this->calculator->calculateDiscountForPoints(3, $rate);

        $this->assertSame(1.0001, $result);
    }

    #[Test]
    public function calculateDiscountForPointsMeetsExactMinimum(): void
    {
        // Minimum is exactly 100 pts; customer provides exactly 100 pts.
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 100);

        $result = $this->calculator->calculateDiscountForPoints(100, $rate);

        $this->assertSame(5.0, $result);
    }

    // -------------------------------------------------------------------------
    // calculatePointsForDiscount
    // -------------------------------------------------------------------------

    #[Test]
    public function calculatePointsForDiscountConvertsDiscountAmountToPoints(): void
    {
        // Rate: every 100 pts → $5. Discount $10 = ceil(10/5)=2 steps × 100 pts = 200.
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculatePointsForDiscount(10.0, $rate);

        $this->assertSame(200, $result);
    }

    #[Test]
    public function calculatePointsForDiscountReturnsZeroWhenCurrencyAmountIsZero(): void
    {
        $rate = $this->buildRate(points: 100, currencyAmount: 0.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculatePointsForDiscount(10.0, $rate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculatePointsForDiscountReturnsZeroWhenPointsPerStepIsZero(): void
    {
        $rate = $this->buildRate(points: 0, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculatePointsForDiscount(10.0, $rate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculatePointsForDiscountCeilsPartialSteps(): void
    {
        // Discount $7 with currency_amount=$5 → ceil(7/5)=2 steps × 100 pts = 200.
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculatePointsForDiscount(7.0, $rate);

        $this->assertSame(200, $result);
    }

    #[Test]
    public function calculatePointsForDiscountReturnsZeroWhenDiscountAmountIsZero(): void
    {
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 0);

        $result = $this->calculator->calculatePointsForDiscount(0.0, $rate);

        $this->assertSame(0, $result);
    }

    // -------------------------------------------------------------------------
    // calculateMaxDiscount
    // -------------------------------------------------------------------------

    #[Test]
    public function calculateMaxDiscountReturnsDiscountForSufficientBalance(): void
    {
        // Min 100 pts; customer has 500 pts. 500/100=5 steps × $5 = $25.
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 100);

        $result = $this->calculator->calculateMaxDiscount(500, $rate);

        $this->assertSame(25.0, $result);
    }

    #[Test]
    public function calculateMaxDiscountReturnsZeroWhenBalanceBelowMinimum(): void
    {
        $rate = $this->buildRate(points: 100, currencyAmount: 5.0, minPointsPerOrder: 200);

        $result = $this->calculator->calculateMaxDiscount(100, $rate);

        $this->assertSame(0.0, $result);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * @param int $points
     * @param float $currencyAmount
     * @param int $minPointsPerOrder
     * @return SpendingRateInterface&MockObject
     */
    private function buildRate(
        int $points,
        float $currencyAmount,
        int $minPointsPerOrder,
    ): SpendingRateInterface {
        $rate = $this->createMock(SpendingRateInterface::class);
        $rate->method('getPoints')->willReturn($points);
        $rate->method('getCurrencyAmount')->willReturn($currencyAmount);
        $rate->method('getMinPointsPerOrder')->willReturn($minPointsPerOrder);

        return $rate;
    }
}

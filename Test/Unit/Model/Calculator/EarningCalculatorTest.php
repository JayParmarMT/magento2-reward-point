<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Test\Unit\Model\Calculator;

use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Calculator\EarningCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

/**
 * Unit tests for EarningCalculator
 */
#[AllowMockObjectsWithoutExpectations]
class EarningCalculatorTest extends TestCase
{
    /** @var Config&MockObject */
    private Config $config;

    private EarningCalculator $calculator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->config = $this->createMock(Config::class);

        $this->calculator = new EarningCalculator(
            $this->config,
        );
    }

    // -------------------------------------------------------------------------
    // calculateFromRate — fixed-rate scenario (1 money_step = N points)
    // -------------------------------------------------------------------------

    #[Test]
    public function calculateFromRateReturnsCorrectPointsForFixedRate(): void
    {
        // Rate: every $10 earns 5 points. Order total $100 = 10 steps × 5 = 50 pts.
        $rate = $this->buildRate(moneyStep: 10.0, points: 5, minOrderTotal: null);

        $this->config->method('getRoundingMethod')->willReturn('normal');

        $result = $this->calculator->calculateFromRate(100.0, $rate);

        $this->assertSame(50, $result);
    }

    #[Test]
    public function calculateFromRateCalculatesCorrectlyForPerPriceScenario(): void
    {
        // Per-price scenario: every $1 earns 2 points. Order $15 = 15 steps × 2 = 30 pts.
        $rate = $this->buildRate(moneyStep: 1.0, points: 2, minOrderTotal: null);

        $this->config->method('getRoundingMethod')->willReturn('normal');

        $result = $this->calculator->calculateFromRate(15.0, $rate);

        $this->assertSame(30, $result);
    }

    #[Test]
    public function calculateFromRateCalculatesCorrectlyForPerProfitScenario(): void
    {
        // Per-profit scenario: every $25 earns 10 points. Order $60 = floor(60/25)=2 steps × 10 = 20 pts.
        $rate = $this->buildRate(moneyStep: 25.0, points: 10, minOrderTotal: null);

        $this->config->method('getRoundingMethod')->willReturn('normal');

        $result = $this->calculator->calculateFromRate(60.0, $rate);

        $this->assertSame(20, $result);
    }

    #[Test]
    public function calculateFromRateReturnsZeroWhenMoneyStepIsZero(): void
    {
        $rate = $this->buildRate(moneyStep: 0.0, points: 5, minOrderTotal: null);

        $result = $this->calculator->calculateFromRate(100.0, $rate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculateFromRateReturnsZeroWhenMoneyStepIsNegative(): void
    {
        $rate = $this->buildRate(moneyStep: -1.0, points: 5, minOrderTotal: null);

        $result = $this->calculator->calculateFromRate(100.0, $rate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculateFromRateReturnsZeroWhenOrderAmountIsZero(): void
    {
        $rate = $this->buildRate(moneyStep: 10.0, points: 5, minOrderTotal: null);

        $this->config->method('getRoundingMethod')->willReturn('normal');

        $result = $this->calculator->calculateFromRate(0.0, $rate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculateFromRateReturnsZeroWhenOrderAmountBelowMinOrderTotal(): void
    {
        // Min order total is $50 but order is only $30.
        $rate = $this->buildRate(moneyStep: 10.0, points: 5, minOrderTotal: 50.0);

        $result = $this->calculator->calculateFromRate(30.0, $rate);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function calculateFromRateCalculatesWhenOrderAmountMeetsMinOrderTotal(): void
    {
        // Min order total is $50 and order is exactly $50. 50/10 = 5 steps × 5 = 25 pts.
        $rate = $this->buildRate(moneyStep: 10.0, points: 5, minOrderTotal: 50.0);

        $this->config->method('getRoundingMethod')->willReturn('normal');

        $result = $this->calculator->calculateFromRate(50.0, $rate);

        $this->assertSame(25, $result);
    }

    #[Test]
    public function calculateFromRatePassesStoreIdToConfig(): void
    {
        $rate = $this->buildRate(moneyStep: 10.0, points: 5, minOrderTotal: null);

        $this->config
            ->expects($this->once())
            ->method('getRoundingMethod')
            ->with(3)
            ->willReturn('normal');

        $this->calculator->calculateFromRate(100.0, $rate, 3);
    }

    // -------------------------------------------------------------------------
    // roundPoints
    // -------------------------------------------------------------------------

    #[Test]
    public function roundPointsAppliesNormalRoundingWhenExactlyHalf(): void
    {
        // 0.5 rounds up under "normal" (>= 0.5 → ceil)
        $result = $this->calculator->roundPoints(0.5, 'normal');

        $this->assertSame(1, $result);
    }

    #[Test]
    public function roundPointsAppliesNormalRoundingDownWhenBelowHalf(): void
    {
        // Normal mode: $points >= 0.5 → ceil, else floor.
        // 0.4 < 0.5 → floor(0.4) = 0
        $result = $this->calculator->roundPoints(0.4, 'normal');

        $this->assertSame(0, $result);
    }

    #[Test]
    public function roundPointsAppliesNormalRoundingUpWhenAboveHalf(): void
    {
        // 2.6 >= 0.5 → ceil(2.6) = 3
        $result = $this->calculator->roundPoints(2.6, 'normal');

        $this->assertSame(3, $result);
    }

    #[Test]
    public function roundPointsNormalRoundsCeilForAnyValueAboveOrEqualHalf(): void
    {
        // 2.4 >= 0.5 → ceil(2.4) = 3 (not PHP_ROUND_HALF_UP which would give 2)
        $result = $this->calculator->roundPoints(2.4, 'normal');

        $this->assertSame(3, $result);
    }

    #[Test]
    public function roundPointsAlwaysRoundsUpWhenMethodIsUp(): void
    {
        $result = $this->calculator->roundPoints(2.1, 'up');

        $this->assertSame(3, $result);
    }

    #[Test]
    public function roundPointsAlwaysRoundsDownWhenMethodIsDown(): void
    {
        $result = $this->calculator->roundPoints(2.9, 'down');

        $this->assertSame(2, $result);
    }

    #[Test]
    public function roundPointsReturnsIntegerUnchangedForWholeNumber(): void
    {
        $result = $this->calculator->roundPoints(10.0, 'normal');

        $this->assertSame(10, $result);
    }

    #[Test]
    public function roundPointsFallsBackToNormalRoundingForUnknownMethod(): void
    {
        // Unknown method hits the default match arm which uses normal rounding.
        $result = $this->calculator->roundPoints(3.6, 'unknown');

        $this->assertSame(4, $result);
    }

    // -------------------------------------------------------------------------
    // max_points cap (via rounding — points capped when moneyStep produces overflow)
    // -------------------------------------------------------------------------

    #[Test]
    public function calculateFromRateRoundsRawPointsUsingConfiguredMethod(): void
    {
        // Every $3 earns 1 point. Order $10 = floor(10/3)=3 steps × 1 = 3 pts (exact integer).
        $rate = $this->buildRate(moneyStep: 3.0, points: 1, minOrderTotal: null);

        $this->config->method('getRoundingMethod')->willReturn('up');

        $result = $this->calculator->calculateFromRate(10.0, $rate);

        // floor(10/3)=3, rawPoints=3.0 → ceil(3.0)=3
        $this->assertSame(3, $result);
    }

    #[Test]
    public function calculateFromRateRoundsUpWhenConfiguredAndFractionalStepsExist(): void
    {
        // Fractional points scenario: money_step=7, points=3. Order $10 = floor(10/7)=1 step × 3 = 3 pts.
        $rate = $this->buildRate(moneyStep: 7.0, points: 3, minOrderTotal: null);

        $this->config->method('getRoundingMethod')->willReturn('up');

        $result = $this->calculator->calculateFromRate(10.0, $rate);

        $this->assertSame(3, $result);
    }

    // -------------------------------------------------------------------------
    // Helper builders
    // -------------------------------------------------------------------------

    /**
     * @param float $moneyStep
     * @param int $points
     * @param float|null $minOrderTotal
     * @return EarningRateInterface&MockObject
     */
    private function buildRate(
        float $moneyStep,
        int $points,
        ?float $minOrderTotal,
    ): EarningRateInterface {
        $rate = $this->createMock(EarningRateInterface::class);
        $rate->method('getMoneyStep')->willReturn($moneyStep);
        $rate->method('getPoints')->willReturn($points);
        $rate->method('getMinOrderTotal')->willReturn($minOrderTotal);

        return $rate;
    }
}

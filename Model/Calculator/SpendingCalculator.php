<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Calculator;

use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;

/**
 * Spending Calculator — converts reward points to a discount amount
 */
class SpendingCalculator
{
    /**
     * Calculate maximum discount for an available points balance
     *
     * @param int $availablePoints
     * @param SpendingRateInterface $rate
     * @return float
     */
    public function calculateMaxDiscount(int $availablePoints, SpendingRateInterface $rate): float
    {
        $minPoints = $rate->getMinPointsPerOrder();

        if ($availablePoints < $minPoints) {
            return 0.0;
        }

        return $this->calculateDiscountForPoints($availablePoints, $rate);
    }

    /**
     * Calculate how many points are needed for a given discount amount
     *
     * @param float $discountAmount
     * @param SpendingRateInterface $rate
     * @return int
     */
    public function calculatePointsForDiscount(float $discountAmount, SpendingRateInterface $rate): int
    {
        $currencyAmount = $rate->getCurrencyAmount();
        $pointsPerStep = $rate->getPoints();

        if ($currencyAmount <= 0 || $pointsPerStep <= 0) {
            return 0;
        }

        $steps = (int) ceil($discountAmount / $currencyAmount);

        return $steps * $pointsPerStep;
    }

    /**
     * Calculate discount amount for a given number of points
     *
     * @param int $points
     * @param SpendingRateInterface $rate
     * @return float
     */
    public function calculateDiscountForPoints(int $points, SpendingRateInterface $rate): float
    {
        $minPoints = $rate->getMinPointsPerOrder();

        if ($points < $minPoints) {
            return 0.0;
        }

        $pointsPerStep = $rate->getPoints();
        $currencyAmount = $rate->getCurrencyAmount();

        if ($pointsPerStep <= 0 || $currencyAmount <= 0) {
            return 0.0;
        }

        $steps = floor($points / $pointsPerStep);

        return round($steps * $currencyAmount, 4);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Calculator;

use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Earning Calculator — converts an order amount to reward points
 */
class EarningCalculator
{
    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config,
    ) {
    }

    /**
     * Calculate earned points for an order amount using a rate
     *
     * @param float $orderAmount
     * @param EarningRateInterface $rate
     * @param int|null $storeId
     * @return int
     */
    public function calculateFromRate(
        float $orderAmount,
        EarningRateInterface $rate,
        ?int $storeId = null,
    ): int {
        $minOrderTotal = $rate->getMinOrderTotal();

        if ($minOrderTotal !== null && $orderAmount < $minOrderTotal) {
            return 0;
        }

        $moneyStep = $rate->getMoneyStep();

        if ($moneyStep <= 0) {
            return 0;
        }

        $steps = floor($orderAmount / $moneyStep);
        $rawPoints = $steps * $rate->getPoints();

        // Advanced: "Round Down Points" overrides the rounding method setting
        $method = $this->config->isRoundDown($storeId)
            ? 'down'
            : $this->config->getRoundingMethod($storeId);

        return $this->roundPoints($rawPoints, $method);
    }

    /**
     * Round a points value using the configured method
     *
     * @param float $points
     * @param string $method normal|up|down
     * @return int
     */
    public function roundPoints(float $points, string $method): int
    {
        return match ($method) {
            'up' => (int) ceil($points),
            'down' => (int) floor($points),
            default => $points >= 0.5 ? (int) ceil($points) : (int) floor($points),
        };
    }
}

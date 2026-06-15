<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Quote;

use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Api\Data\CartInterface;

/**
 * Manages reward points extension attributes on the quote
 */
class RewardPoints
{
    /**
     * @param CartRepositoryInterface $cartRepository
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
    ) {
    }

    /**
     * Get reward points used on the quote
     *
     * @param CartInterface $cart
     * @return int
     */
    public function getRewardPointsUsed(CartInterface $cart): int
    {
        $extensionAttributes = $cart->getExtensionAttributes();

        if ($extensionAttributes && $extensionAttributes->getRewardPointsUsed() !== null) {
            return (int) $extensionAttributes->getRewardPointsUsed();
        }

        return (int) $cart->getData('reward_points_used');
    }

    /**
     * Set reward points used on the quote
     *
     * @param CartInterface $cart
     * @param int $points
     * @return void
     */
    public function setRewardPointsUsed(CartInterface $cart, int $points): void
    {
        $cart->setData('reward_points_used', $points);

        $extensionAttributes = $cart->getExtensionAttributes();

        if ($extensionAttributes) {
            $extensionAttributes->setRewardPointsUsed($points);
        }
    }

    /**
     * Get reward points discount on the quote
     *
     * @param CartInterface $cart
     * @return float
     */
    public function getRewardPointsDiscount(CartInterface $cart): float
    {
        $extensionAttributes = $cart->getExtensionAttributes();

        if ($extensionAttributes && $extensionAttributes->getRewardPointsDiscount() !== null) {
            return (float) $extensionAttributes->getRewardPointsDiscount();
        }

        return (float) $cart->getData('reward_points_discount');
    }

    /**
     * Set reward points discount on the quote
     *
     * @param CartInterface $cart
     * @param float $discount
     * @return void
     */
    public function setRewardPointsDiscount(CartInterface $cart, float $discount): void
    {
        $cart->setData('reward_points_discount', $discount);

        $extensionAttributes = $cart->getExtensionAttributes();

        if ($extensionAttributes) {
            $extensionAttributes->setRewardPointsDiscount($discount);
        }
    }
}

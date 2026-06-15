<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Cart Reward Points Management Interface
 *
 * @api
 */
interface CartManagementInterface
{
    /**
     * Apply reward points to a cart
     *
     * @param string $cartId
     * @param int $points
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function applyPoints(string $cartId, int $points): bool;

    /**
     * Remove reward points from a cart
     *
     * @param string $cartId
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function removePoints(string $cartId): bool;
}

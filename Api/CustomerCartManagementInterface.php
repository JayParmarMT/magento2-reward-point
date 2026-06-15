<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Customer-facing cart reward-points management.
 *
 * All methods receive `customerId` injected by the REST framework via the
 * %customer_id% parameter binding, which means the authenticated customer's ID
 * is always used — callers cannot supply a different customer ID.
 *
 * The `cartId` parameter is the *masked* quote UUID (as used by
 * Magento's own /V1/carts/mine endpoints).  The implementation must verify
 * that the resolved quote belongs to the authenticated customer before
 * performing any mutation, preventing IDOR attacks.
 *
 * @api
 */
interface CustomerCartManagementInterface
{
    /**
     * Apply reward points to the authenticated customer's cart.
     *
     * @param int $customerId Injected automatically by the REST framework via %customer_id%
     * @param int $points Number of points to apply (must be > 0)
     * @return bool
     * @throws NoSuchEntityException  When the cart does not exist or does not belong to the customer
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function applyPoints(int $customerId, int $points): bool;

    /**
     * Remove reward points from the authenticated customer's cart.
     *
     * @param int $customerId Injected automatically by the REST framework via %customer_id%
     * @return bool
     * @throws NoSuchEntityException  When the cart does not exist or does not belong to the customer
     * @throws LocalizedException
     */
    public function removePoints(int $customerId): bool;
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;

/**
 * Customer-facing balance service — resolves website from session context.
 * Used by the REST /balance/me endpoint so callers need not supply websiteId.
 *
 * @api
 */
interface CustomerBalanceInterface
{
    /**
     * Get the authenticated customer's reward points balance.
     * Website is resolved from the current store context.
     *
     * @param int $customerId Injected automatically by the REST framework via %customer_id%
     * @return int Current points balance
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getMyBalance(int $customerId): int;
}

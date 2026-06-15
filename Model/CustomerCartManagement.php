<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Quote\Model\MaskedQuoteIdToQuoteIdInterface;
use Meetanshi\RewardPoints\Api\CartManagementInterface;
use Meetanshi\RewardPoints\Api\CustomerCartManagementInterface;

/**
 * Customer-facing cart reward-points management.
 *
 * Enforces ownership: verifies the authenticated customer owns the cart before
 * delegating to CartManagement.  This prevents IDOR (Insecure Direct Object
 * Reference) attacks where one customer could manipulate another customer's cart
 * by guessing a cart ID.
 *
 * The `customerId` parameter is supplied by the REST framework via the
 * %customer_id% parameter binding in webapi.xml, so it always reflects the
 * authenticated session — callers cannot forge it.
 */
class CustomerCartManagement implements CustomerCartManagementInterface
{
    /**
     * @param CartRepositoryInterface $cartRepository
     * @param CartManagementInterface $cartManagement
     * @param MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId
     */
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly CartManagementInterface $cartManagement,
        private readonly MaskedQuoteIdToQuoteIdInterface $maskedQuoteIdToQuoteId,
    ) {
    }

    /**
     * Apply reward points to the authenticated customer's active cart.
     *
     * @param int $customerId Injected automatically by the REST framework via %customer_id%
     * @param int $points
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     * @throws CouldNotSaveException
     */
    public function applyPoints(int $customerId, int $points): bool
    {
        $quoteId = $this->resolveCustomerCartId($customerId);

        return $this->cartManagement->applyPoints((string) $quoteId, $points);
    }

    /**
     * Remove reward points from the authenticated customer's active cart.
     *
     * @param int $customerId Injected automatically by the REST framework via %customer_id%
     * @return bool
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function removePoints(int $customerId): bool
    {
        $quoteId = $this->resolveCustomerCartId($customerId);

        return $this->cartManagement->removePoints((string) $quoteId);
    }

    /**
     * Resolve the active cart ID for the given customer.
     *
     * Fetches the customer's active quote and returns its numeric ID.
     * Throws NoSuchEntityException if the customer has no active cart.
     *
     * @param int $customerId
     * @return int
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    private function resolveCustomerCartId(int $customerId): int
    {
        try {
            $quote = $this->cartRepository->getActiveForCustomer($customerId);
        } catch (NoSuchEntityException $e) {
            throw new NoSuchEntityException(
                __('No active cart found for the current customer.'),
                $e,
            );
        }

        return (int) $quote->getId();
    }
}

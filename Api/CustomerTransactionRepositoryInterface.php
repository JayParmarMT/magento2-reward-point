<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TransactionSearchResultsInterface;

/**
 * Customer-facing transaction repository.
 *
 * Exposes only the transactions that belong to the authenticated customer.
 * The `customerId` parameter is injected by the REST framework via the
 * %customer_id% binding — callers cannot supply a different customer ID.
 *
 * @api
 */
interface CustomerTransactionRepositoryInterface
{
    /**
     * List the authenticated customer's reward-point transactions.
     *
     * The implementation MUST append a customer_id filter to the supplied
     * SearchCriteria before delegating to the underlying repository, ensuring
     * that no other customer's transactions can be returned regardless of what
     * filters the caller provides.
     *
     * @param int $customerId Injected automatically by the REST framework via %customer_id%
     * @param SearchCriteriaInterface $searchCriteria
     * @return TransactionSearchResultsInterface
     * @throws NoSuchEntityException
     * @throws LocalizedException
     */
    public function getMyTransactions(
        int $customerId,
        SearchCriteriaInterface $searchCriteria,
    ): TransactionSearchResultsInterface;
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\FilterGroupBuilder;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\CustomerTransactionRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionSearchResultsInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;

/**
 * Customer-facing transaction repository.
 *
 * Wraps the admin TransactionRepositoryInterface and forces a customer_id
 * filter onto every query so a customer can only ever read their own records.
 */
class CustomerTransactionRepository implements CustomerTransactionRepositoryInterface
{
    /**
     * @param TransactionRepositoryInterface $transactionRepository
     * @param FilterBuilder $filterBuilder
     * @param FilterGroupBuilder $filterGroupBuilder
     */
    public function __construct(
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly FilterBuilder $filterBuilder,
        private readonly FilterGroupBuilder $filterGroupBuilder,
    ) {
    }

    /**
     * List the authenticated customer's reward-point transactions.
     *
     * Forces a customer_id = {customerId} filter on the SearchCriteria so that
     * no other customer's records can ever be returned, regardless of any filters
     * supplied by the API caller.
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
    ): TransactionSearchResultsInterface {
        // Build a mandatory customer_id filter and append it as its own filter group.
        // Filter groups within Magento's SearchCriteria are combined with AND,
        // so this group cannot be overridden by the caller's own filter groups.
        $customerFilter = $this->filterBuilder
            ->setField(TransactionInterface::CUSTOMER_ID)
            ->setConditionType('eq')
            ->setValue($customerId)
            ->create();

        $customerFilterGroup = $this->filterGroupBuilder
            ->addFilter($customerFilter)
            ->create();

        // Append to any existing filter groups (they are AND-ed together).
        $existingGroups = $searchCriteria->getFilterGroups() ?? [];
        $searchCriteria->setFilterGroups(array_merge($existingGroups, [$customerFilterGroup]));

        return $this->transactionRepository->getList($searchCriteria);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;

/**
 * Spending Rate Repository Interface
 *
 * @api
 */
interface SpendingRateRepositoryInterface
{
    /**
     * Save spending rate
     *
     * @param SpendingRateInterface $spendingRate
     * @param int[] $websiteIds
     * @param int[] $customerGroupIds
     * @return SpendingRateInterface
     * @throws CouldNotSaveException
     */
    public function save(
        SpendingRateInterface $spendingRate,
        array $websiteIds,
        array $customerGroupIds,
    ): SpendingRateInterface;

    /**
     * Get spending rate by ID
     *
     * @param int $rateId
     * @return SpendingRateInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $rateId): SpendingRateInterface;

    /**
     * Delete spending rate
     *
     * @param SpendingRateInterface $spendingRate
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(SpendingRateInterface $spendingRate): bool;

    /**
     * Delete spending rate by ID
     *
     * @param int $rateId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $rateId): bool;

    /**
     * Get list of spending rates
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}

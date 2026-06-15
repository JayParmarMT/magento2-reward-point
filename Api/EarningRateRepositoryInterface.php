<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;

/**
 * Earning Rate Repository Interface
 *
 * @api
 */
interface EarningRateRepositoryInterface
{
    /**
     * Save earning rate
     *
     * @param EarningRateInterface $earningRate
     * @param int[] $websiteIds
     * @param int[] $customerGroupIds
     * @return EarningRateInterface
     * @throws CouldNotSaveException
     */
    public function save(
        EarningRateInterface $earningRate,
        array $websiteIds,
        array $customerGroupIds,
    ): EarningRateInterface;

    /**
     * Get earning rate by ID
     *
     * @param int $rateId
     * @return EarningRateInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $rateId): EarningRateInterface;

    /**
     * Delete earning rate
     *
     * @param EarningRateInterface $earningRate
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(EarningRateInterface $earningRate): bool;

    /**
     * Delete earning rate by ID
     *
     * @param int $rateId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $rateId): bool;

    /**
     * Get list of earning rates
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface;
}

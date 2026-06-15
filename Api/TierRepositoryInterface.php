<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\Data\TierSearchResultsInterface;

/**
 * Tier repository interface
 *
 * @api
 */
interface TierRepositoryInterface
{
    /**
     * Save tier
     *
     * @param TierInterface $tier
     * @return TierInterface
     * @throws CouldNotSaveException
     */
    public function save(TierInterface $tier): TierInterface;

    /**
     * Get tier by ID
     *
     * @param int $tierId
     * @return TierInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $tierId): TierInterface;

    /**
     * Delete tier
     *
     * @param TierInterface $tier
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(TierInterface $tier): bool;

    /**
     * Delete tier by ID
     *
     * @param int $tierId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $tierId): bool;

    /**
     * Get list of tiers
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return TierSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TierSearchResultsInterface;

    /**
     * Get active tiers where min_points <= given points, sorted by min_points desc
     *
     * @param int $points
     * @return TierInterface[]
     */
    public function getActiveByMinPoints(int $points): array;
}

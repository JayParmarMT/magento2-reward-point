<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Api\Data\AccountSearchResultsInterface;

/**
 * Reward Points Account Repository Interface
 *
 * @api
 */
interface AccountRepositoryInterface
{
    /**
     * Save account
     *
     * @param AccountInterface $account
     * @return AccountInterface
     * @throws CouldNotSaveException
     */
    public function save(AccountInterface $account): AccountInterface;

    /**
     * Get account by ID
     *
     * @param int $accountId
     * @return AccountInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $accountId): AccountInterface;

    /**
     * Get account by customer and website
     *
     * @param int $customerId
     * @param int $websiteId
     * @return AccountInterface
     * @throws NoSuchEntityException
     */
    public function getByCustomer(int $customerId, int $websiteId): AccountInterface;

    /**
     * Get or create account for customer + website
     *
     * @param int $customerId
     * @param int $websiteId
     * @return AccountInterface
     * @throws CouldNotSaveException
     */
    public function getOrCreate(int $customerId, int $websiteId): AccountInterface;

    /**
     * Delete account
     *
     * @param AccountInterface $account
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(AccountInterface $account): bool;

    /**
     * Delete account by ID
     *
     * @param int $accountId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $accountId): bool;

    /**
     * Get list
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return AccountSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AccountSearchResultsInterface;
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionSearchResultsInterface;

/**
 * Reward Points Transaction Repository Interface
 *
 * @api
 */
interface TransactionRepositoryInterface
{
    /**
     * Save transaction
     *
     * @param TransactionInterface $transaction
     * @return TransactionInterface
     * @throws CouldNotSaveException
     */
    public function save(TransactionInterface $transaction): TransactionInterface;

    /**
     * Get transaction by ID
     *
     * @param int $transactionId
     * @return TransactionInterface
     * @throws NoSuchEntityException
     */
    public function getById(int $transactionId): TransactionInterface;

    /**
     * Delete transaction
     *
     * @param TransactionInterface $transaction
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(TransactionInterface $transaction): bool;

    /**
     * Get list
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return TransactionSearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TransactionSearchResultsInterface;
}

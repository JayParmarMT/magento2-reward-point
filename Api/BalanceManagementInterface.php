<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Exception\InsufficientBalanceException;

/**
 * Balance Management Service Contract
 *
 * @api
 */
interface BalanceManagementInterface
{
    /**
     * Add points to customer account (earn or manual credit)
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $points Must be > 0
     * @param string $actionCode
     * @param string|null $comment
     * @param int|null $expireAfterDays 0/null = use global config
     * @param bool $notifyCustomer
     * @param array<string, mixed> $extraData
     * @return TransactionInterface
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function addPoints(
        int $customerId,
        int $websiteId,
        int $points,
        string $actionCode,
        ?string $comment = null,
        ?int $expireAfterDays = null,
        bool $notifyCustomer = false,
        array $extraData = [],
    ): TransactionInterface;

    /**
     * Subtract points from customer account (spend or deduct)
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int $points Must be > 0
     * @param string $actionCode
     * @param string|null $comment
     * @param array<string, mixed> $extraData
     * @return TransactionInterface
     * @throws InsufficientBalanceException
     * @throws CouldNotSaveException
     * @throws LocalizedException
     */
    public function subtractPoints(
        int $customerId,
        int $websiteId,
        int $points,
        string $actionCode,
        ?string $comment = null,
        array $extraData = [],
    ): TransactionInterface;

    /**
     * Expire points (transition pending/active transactions to expired)
     *
     * @param int $customerId
     * @param int $websiteId
     * @param int[] $transactionIds
     * @return int Number of transactions expired
     * @throws LocalizedException
     */
    public function expirePoints(int $customerId, int $websiteId, array $transactionIds): int;

    /**
     * Recompute balance from ledger (repair tool)
     *
     * @param int $customerId
     * @param int $websiteId
     * @return int Recomputed balance
     * @throws NoSuchEntityException
     * @throws CouldNotSaveException
     */
    public function recomputeBalance(int $customerId, int $websiteId): int;

    /**
     * Get current balance
     *
     * @param int $customerId
     * @param int $websiteId
     * @return int
     * @throws NoSuchEntityException
     */
    public function getBalance(int $customerId, int $websiteId): int;
}

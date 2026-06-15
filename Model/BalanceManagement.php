<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Exception\InsufficientBalanceException;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\ResourceModel\Account as AccountResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;
use Psr\Log\LoggerInterface;

/**
 * Balance Management Service — handles all point mutations with row-level locking
 */
class BalanceManagement implements BalanceManagementInterface
{
    /**
     * @param AccountRepositoryInterface $accountRepository
     * @param AccountResource $accountResource
     * @param AccountFactory $accountFactory
     * @param TransactionFactory $transactionFactory
     * @param TransactionResource $transactionResource
     * @param Config $config
     * @param TimezoneInterface $timezone
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountResource $accountResource,
        private readonly AccountFactory $accountFactory,
        private readonly TransactionFactory $transactionFactory,
        private readonly TransactionResource $transactionResource,
        private readonly Config $config,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
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
    ): TransactionInterface {
        if ($points <= 0) {
            throw new LocalizedException(
                new Phrase('Points to add must be greater than zero. Got: %1', [$points]),
            );
        }

        $connection = $this->accountResource->getConnection();
        $connection->beginTransaction();

        try {
            // Acquire row-level lock to prevent lost updates
            $account = $this->lockAndLoadAccount($customerId, $websiteId, $connection);

            $currentBalance = $account->getPointsBalance();
            $maxBalance = $this->config->getMaxBalance($websiteId);

            // Enforce max balance cap for non-admin actions
            $effectivePoints = $points;

            if ($maxBalance > 0 && ($currentBalance + $points) > $maxBalance) {
                $effectivePoints = max(0, $maxBalance - $currentBalance);

                if ($effectivePoints < $points) {
                    $this->logger->warning(
                        'RewardPoints: max balance cap applied',
                        [
                            'customer_id' => $customerId,
                            'website_id' => $websiteId,
                            'requested' => $points,
                            'credited' => $effectivePoints,
                            'max_balance' => $maxBalance,
                        ],
                    );
                }
            }

            // Calculate expiration
            $expiresAt = $this->calculateExpiresAt($expireAfterDays, $websiteId);

            // Determine initial status based on holding period.
            // When holdingDays > 0 the points are PENDING until activated by cron;
            // the live balance only increases once they become ACTIVE.
            $holdingDays = isset($extraData['holding_days'])
                ? (int) $extraData['holding_days']
                : $this->config->getHoldingDays();

            if ($holdingDays > 0) {
                $status = TransactionInterface::STATUS_PENDING;
                // Pending transactions do not count toward the spendable balance yet
                $newBalance = $currentBalance;
            } else {
                $status = TransactionInterface::STATUS_ACTIVE;
                $newBalance = $currentBalance + $effectivePoints;
            }

            // Create the transaction
            $transaction = $this->transactionFactory->create();
            $transaction->setAccountId((int) $account->getAccountId());
            $transaction->setCustomerId($customerId);
            $transaction->setPointsDelta($effectivePoints);
            $transaction->setPointsBalanceAfter($newBalance);
            $transaction->setActionCode($actionCode);
            $transaction->setStatus($status);
            $transaction->setComment($comment);
            $transaction->setExpiresAt($expiresAt);

            if (isset($extraData['store_id'])) {
                $transaction->setStoreId((int) $extraData['store_id']);
            }

            if (isset($extraData['order_id'])) {
                $transaction->setOrderId((int) $extraData['order_id']);
            }

            if (isset($extraData['creditmemo_id'])) {
                $transaction->setCreditmemoId((int) $extraData['creditmemo_id']);
            }

            if (isset($extraData['rule_id'])) {
                $transaction->setRuleId((int) $extraData['rule_id']);
            }

            if (isset($extraData['rule_type'])) {
                $transaction->setRuleType((string) $extraData['rule_type']);
            }

            if (isset($extraData['admin_user_id'])) {
                $transaction->setAdminUserId((int) $extraData['admin_user_id']);
            }

            if (isset($extraData['admin_user_name'])) {
                $transaction->setAdminUserName((string) $extraData['admin_user_name']);
            }

            $this->transactionResource->save($transaction);

            // Only update the live balance when points are immediately active
            if ($status === TransactionInterface::STATUS_ACTIVE && $effectivePoints > 0) {
                $account->setPointsBalance($newBalance);
                $account->setTotalEarned($account->getTotalEarned() + $effectivePoints);
                $this->accountResource->save($account);
            } elseif ($status === TransactionInterface::STATUS_PENDING && $effectivePoints > 0) {
                // total_earned increments immediately so the tier engine can track lifetime earnings
                $account->setTotalEarned($account->getTotalEarned() + $effectivePoints);
                $this->accountResource->save($account);
            }

            $connection->commit();

            return $transaction;
        } catch (LocalizedException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new CouldNotSaveException(
                new Phrase('Could not add reward points: %1', [$e->getMessage()]),
                $e,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function subtractPoints(
        int $customerId,
        int $websiteId,
        int $points,
        string $actionCode,
        ?string $comment = null,
        array $extraData = [],
    ): TransactionInterface {
        if ($points <= 0) {
            throw new LocalizedException(
                new Phrase('Points to subtract must be greater than zero. Got: %1', [$points]),
            );
        }

        $connection = $this->accountResource->getConnection();
        $connection->beginTransaction();

        try {
            $account = $this->lockAndLoadAccount($customerId, $websiteId, $connection);
            $currentBalance = $account->getPointsBalance();

            if ($currentBalance < $points) {
                $connection->rollBack();
                throw new InsufficientBalanceException(
                    new Phrase(
                        'Insufficient reward points balance. Available: %1, Requested: %2',
                        [$currentBalance, $points],
                    ),
                );
            }

            $newBalance = $currentBalance - $points;

            $transaction = $this->transactionFactory->create();
            $transaction->setAccountId((int) $account->getAccountId());
            $transaction->setCustomerId($customerId);
            $transaction->setPointsDelta(-$points);
            $transaction->setPointsBalanceAfter($newBalance);
            $transaction->setActionCode($actionCode);
            $transaction->setStatus(TransactionInterface::STATUS_ACTIVE);
            $transaction->setComment($comment);

            if (isset($extraData['store_id'])) {
                $transaction->setStoreId((int) $extraData['store_id']);
            }

            if (isset($extraData['order_id'])) {
                $transaction->setOrderId((int) $extraData['order_id']);
            }

            if (isset($extraData['creditmemo_id'])) {
                $transaction->setCreditmemoId((int) $extraData['creditmemo_id']);
            }

            if (isset($extraData['rule_id'])) {
                $transaction->setRuleId((int) $extraData['rule_id']);
            }

            if (isset($extraData['rule_type'])) {
                $transaction->setRuleType((string) $extraData['rule_type']);
            }

            if (isset($extraData['admin_user_id'])) {
                $transaction->setAdminUserId((int) $extraData['admin_user_id']);
            }

            if (isset($extraData['admin_user_name'])) {
                $transaction->setAdminUserName((string) $extraData['admin_user_name']);
            }

            $this->transactionResource->save($transaction);

            $account->setPointsBalance($newBalance);
            $account->setTotalSpent($account->getTotalSpent() + $points);
            $this->accountResource->save($account);

            $connection->commit();

            return $transaction;
        } catch (InsufficientBalanceException $e) {
            throw $e;
        } catch (LocalizedException $e) {
            $connection->rollBack();
            throw $e;
        } catch (\Exception $e) {
            $connection->rollBack();
            throw new CouldNotSaveException(
                new Phrase('Could not subtract reward points: %1', [$e->getMessage()]),
                $e,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function expirePoints(int $customerId, int $websiteId, array $transactionIds): int
    {
        if (empty($transactionIds)) {
            return 0;
        }

        $connection = $this->accountResource->getConnection();
        $connection->beginTransaction();

        try {
            $account = $this->lockAndLoadAccount($customerId, $websiteId, $connection);

            $txnTable = $this->transactionResource->getMainTable();
            $select = $connection->select()
                ->from($txnTable, ['transaction_id', 'points_delta'])
                ->where('transaction_id IN (?)', $transactionIds)
                ->where('account_id = ?', $account->getAccountId())
                ->where('status = ?', TransactionInterface::STATUS_ACTIVE)
                ->where('points_delta > 0');

            $rows = $connection->fetchAll($select);

            if (empty($rows)) {
                $connection->rollBack();
                return 0;
            }

            $totalExpired = 0;

            foreach ($rows as $row) {
                $totalExpired += (int) $row['points_delta'];
            }

            $txnIds = array_column($rows, 'transaction_id');
            $connection->update(
                $txnTable,
                ['status' => TransactionInterface::STATUS_EXPIRED],
                ['transaction_id IN (?)' => $txnIds],
            );

            $newBalance = max(0, $account->getPointsBalance() - $totalExpired);
            $connection->update(
                $this->accountResource->getMainTable(),
                ['points_balance' => $newBalance],
                ['account_id = ?' => $account->getAccountId()],
            );

            $connection->commit();

            return count($rows);
        } catch (\Exception $e) {
            $connection->rollBack();
            $this->logger->error('RewardPoints: expirePoints failed', ['exception' => $e]);
            throw new LocalizedException(
                new Phrase('Could not expire reward points: %1', [$e->getMessage()]),
                $e,
            );
        }
    }

    /**
     * {@inheritdoc}
     */
    public function recomputeBalance(int $customerId, int $websiteId): int
    {
        $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
        $connection = $this->accountResource->getConnection();

        $txnTable = $this->transactionResource->getMainTable();

        $select = $connection->select()
            ->from($txnTable, new \Magento\Framework\DB\Expr('COALESCE(SUM(points_delta), 0)'))
            ->where('account_id = ?', $account->getAccountId())
            ->where('status = ?', TransactionInterface::STATUS_ACTIVE);

        $balance = (int) $connection->fetchOne($select);
        $balance = max(0, $balance);

        $account->setPointsBalance($balance);
        $this->accountRepository->save($account);

        return $balance;
    }

    /**
     * {@inheritdoc}
     */
    public function getBalance(int $customerId, int $websiteId): int
    {
        $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
        return $account->getPointsBalance();
    }

    /**
     * Lock the account row for update and return the loaded account model
     *
     * @param int $customerId
     * @param int $websiteId
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return \Meetanshi\RewardPoints\Model\Account
     * @throws CouldNotSaveException
     */
    private function lockAndLoadAccount(
        int $customerId,
        int $websiteId,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
    ): \Meetanshi\RewardPoints\Model\Account {
        $table = $this->accountResource->getMainTable();

        // Acquire row-level lock via SELECT ... FOR UPDATE
        $select = $connection->select()
            ->from($table)
            ->where('customer_id = ?', $customerId)
            ->where('website_id = ?', $websiteId)
            ->forUpdate(true);

        $data = $connection->fetchRow($select);

        if (!$data) {
            // Auto-create account if it doesn't exist yet
            $connection->insert($table, [
                'customer_id' => $customerId,
                'website_id' => $websiteId,
                'points_balance' => 0,
                'total_earned' => 0,
                'total_spent' => 0,
                'is_enabled' => 1,
            ]);

            $data = $connection->fetchRow(
                $connection->select()
                    ->from($table)
                    ->where('customer_id = ?', $customerId)
                    ->where('website_id = ?', $websiteId)
                    ->forUpdate(true),
            );
        }

        /** @var \Meetanshi\RewardPoints\Model\Account $account */
        $account = $this->accountFactory->create();
        $account->setData($data);

        return $account;
    }

    /**
     * Calculate expiration timestamp string
     *
     * @param int|null $expireAfterDays
     * @param int $websiteId
     * @return string|null
     */
    private function calculateExpiresAt(?int $expireAfterDays, int $websiteId): ?string
    {
        // null or 0 both mean "use the global config default".
        // Using ?? alone is insufficient because 0 ?? config evaluates to 0 (not null),
        // bypassing the config fallback and incorrectly treating the transaction as non-expiring.
        $days = ($expireAfterDays !== null && $expireAfterDays > 0)
            ? $expireAfterDays
            : $this->config->getPointsExpireDays($websiteId);

        if ($days <= 0) {
            return null;
        }

        return date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }
}

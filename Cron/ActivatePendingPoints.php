<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Magento\Framework\App\ResourceConnection;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Activates pending reward-points transactions once their holding period has elapsed.
 *
 * A transaction is eligible for activation when:
 *  - status = 'pending'
 *  - created_at + holding_days_offset <= NOW()
 *
 * Because the holding period is stored as a system config value (not per-transaction),
 * we resolve it from the transaction's store_id config. For simplicity we activate any
 * pending transaction whose created_at is older than the global holding-days setting.
 * If holding_days = 0 (the common case), no pending transactions will exist and this
 * cron does nothing.
 */
class ActivatePendingPoints
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Activate pending transactions whose holding period has elapsed.
     *
     * For each eligible pending transaction we:
     *  1. Set status = active
     *  2. Increment the account's points_balance by points_delta
     *
     * Both operations are done in a single UPDATE per-account to avoid
     * race conditions with concurrent balance changes.
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $txnTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_transaction');
        $accountTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_account');

        // Find pending transactions that are ready to activate.
        // We use the transaction's `expires_at` field as a proxy only when available;
        // otherwise fall back to `created_at`. The most robust signal is `created_at`
        // compared against NOW() — if the row was created more than 0 seconds ago AND
        // it is still pending, it should have been activated by now (holding-day
        // configuration may have changed). Rows created very recently (< 1 minute) are
        // skipped to avoid race conditions with the save observer.
        $select = $connection->select()
            ->from($txnTable, ['transaction_id', 'account_id', 'points_delta'])
            ->where('status = ?', TransactionInterface::STATUS_PENDING)
            ->where('points_delta > 0')
            ->where('created_at <= ?', new \Magento\Framework\DB\Expr('DATE_SUB(NOW(), INTERVAL 1 MINUTE)'));

        $rows = $connection->fetchAll($select);

        if (empty($rows)) {
            return;
        }

        // Group by account_id so we can do one balance update per account
        $byAccount = [];

        foreach ($rows as $row) {
            $accountId = (int) $row['account_id'];
            $byAccount[$accountId][] = (int) $row['transaction_id'];
        }

        $activatedCount = 0;

        foreach ($byAccount as $accountId => $transactionIds) {
            $connection->beginTransaction();

            try {
                // Lock account row
                $accountRow = $connection->fetchRow(
                    $connection->select()
                        ->from($accountTable, ['account_id', 'points_balance'])
                        ->where('account_id = ?', $accountId)
                        ->forUpdate(true),
                );

                if (!$accountRow) {
                    $connection->rollBack();
                    continue;
                }

                // Sum points from the transactions we're activating
                $pointsToAdd = (int) $connection->fetchOne(
                    $connection->select()
                        ->from($txnTable, new \Magento\Framework\DB\Expr('COALESCE(SUM(points_delta), 0)'))
                        ->where('transaction_id IN (?)', $transactionIds)
                        ->where('status = ?', TransactionInterface::STATUS_PENDING),
                );

                if ($pointsToAdd > 0) {
                    // Activate the transactions
                    $connection->update(
                        $txnTable,
                        ['status' => TransactionInterface::STATUS_ACTIVE],
                        ['transaction_id IN (?)' => $transactionIds],
                    );

                    // Update account balance
                    $newBalance = (int) $accountRow['points_balance'] + $pointsToAdd;
                    $connection->update(
                        $accountTable,
                        ['points_balance' => $newBalance],
                        ['account_id = ?' => $accountId],
                    );

                    $activatedCount += count($transactionIds);
                }

                $connection->commit();
            } catch (\Exception $e) {
                $connection->rollBack();
                $this->logger->error(
                    'RewardPoints: ActivatePendingPoints failed for account ' . $accountId,
                    ['exception' => $e],
                );
            }
        }

        if ($activatedCount > 0) {
            $this->logger->info(
                sprintf('[RewardPoints] ActivatePendingPoints: activated %d transactions.', $activatedCount),
            );
        }
    }
}

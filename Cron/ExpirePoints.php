<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Magento\Framework\App\ResourceConnection;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Expire points cron — marks active transactions as expired when expires_at <= NOW()
 */
class ExpirePoints
{
    private const BATCH_SIZE = 1000;

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
     * Execute cron job
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
        $totalExpired = 0;

        do {
            $select = $connection->select()
                ->from($txnTable, ['transaction_id', 'account_id', 'points_delta'])
                ->where('status = ?', 'active')
                ->where('expires_at IS NOT NULL')
                ->where('expires_at <= NOW()')
                ->limit(self::BATCH_SIZE);

            $rows = $connection->fetchAll($select);

            if (empty($rows)) {
                break;
            }

            $transactionIds = array_column($rows, 'transaction_id');
            $accountAdjustments = [];

            foreach ($rows as $row) {
                $accountId = (int) $row['account_id'];
                $points = (int) $row['points_delta'];
                $accountAdjustments[$accountId] = ($accountAdjustments[$accountId] ?? 0) + $points;
            }

            $connection->update(
                $txnTable,
                ['status' => 'expired'],
                ['transaction_id IN (?)' => $transactionIds],
            );

            foreach ($accountAdjustments as $accountId => $pointsToDeduct) {
                if ($pointsToDeduct > 0) {
                    $connection->query(
                        'UPDATE ' . $accountTable
                        . ' SET points_balance = GREATEST(0, points_balance - ?)'
                        . ' WHERE account_id = ?',
                        [$pointsToDeduct, $accountId],
                    );
                }
            }

            $totalExpired += count($rows);
            $this->logger->info(
                sprintf('[RewardPoints] ExpirePoints batch: expired %d transactions.', count($rows)),
            );
        } while (count($rows) === self::BATCH_SIZE);

        if ($totalExpired > 0) {
            $this->logger->info(
                sprintf('[RewardPoints] ExpirePoints complete: %d transactions expired.', $totalExpired),
            );
        }
    }
}

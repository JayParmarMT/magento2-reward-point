<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Console\Command;

use Magento\Framework\App\ResourceConnection;
use Meetanshi\RewardPoints\Helper\Config;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to display reward points module status
 */
class StatusCommand extends Command
{
    /**
     * @param Config $config
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly Config $config,
        private readonly ResourceConnection $resourceConnection,
    ) {
        parent::__construct();
    }

    /**
     * Configure command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->setName('meetanshi:rewardpoints:status')
            ->setDescription('Display Meetanshi Reward Points module status and statistics');
    }

    /**
     * Execute the command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $connection = $this->resourceConnection->getConnection();
        $enabled = $this->config->isEnabled() ? 'Yes' : 'No';

        $output->writeln('');
        $output->writeln('<info>Meetanshi Reward Points — Module Status</info>');
        $output->writeln(str_repeat('─', 50));

        $output->writeln("Module Enabled:          $enabled");

        // Total accounts
        $accountTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_account');
        $totalAccounts = (int) $connection->fetchOne(
            $connection->select()->from($accountTable, 'COUNT(*)'),
        );
        $output->writeln("Total Accounts:          $totalAccounts");

        // Total transactions
        $txnTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_transaction');
        $totalTxns = (int) $connection->fetchOne(
            $connection->select()->from($txnTable, 'COUNT(*)'),
        );
        $output->writeln("Total Transactions:      $totalTxns");

        // Pending transactions
        $pendingTxns = (int) $connection->fetchOne(
            $connection->select()
                ->from($txnTable, 'COUNT(*)')
                ->where('status = ?', 'pending'),
        );
        $output->writeln("Pending Transactions:    $pendingTxns");

        // Transactions expiring today
        $expiringToday = (int) $connection->fetchOne(
            $connection->select()
                ->from($txnTable, 'COUNT(*)')
                ->where('status IN (?)', ['active', 'pending'])
                ->where('expires_at IS NOT NULL')
                ->where('DATE(expires_at) = CURDATE()'),
        );
        $output->writeln("Expiring Today:          $expiringToday");

        // Active tiers
        $tierTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_tier');
        $activeTiers = (int) $connection->fetchOne(
            $connection->select()
                ->from($tierTable, 'COUNT(*)')
                ->where('is_active = ?', 1),
        );
        $output->writeln("Active Tiers:            $activeTiers");

        $output->writeln(str_repeat('─', 50));
        $output->writeln('');

        return Command::SUCCESS;
    }
}

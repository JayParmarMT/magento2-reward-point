<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Console\Command;

use Meetanshi\RewardPoints\Cron\TierRecalculate;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to recompute customer tiers
 */
class TierRecalculateCommand extends Command
{
    private const OPTION_CUSTOMER_ID = 'customer-id';

    /**
     * @param TierRecalculate $tierRecalculateCron
     */
    public function __construct(
        private readonly TierRecalculate $tierRecalculateCron,
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
        $this->setName('meetanshi:rewardpoints:tier:recalculate')
            ->setDescription('Recompute customer tiers')
            ->addOption(
                self::OPTION_CUSTOMER_ID,
                null,
                InputOption::VALUE_OPTIONAL,
                'Recalculate for a specific customer ID only',
            );
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
        $customerId = $input->getOption(self::OPTION_CUSTOMER_ID);

        if ($customerId !== null) {
            $output->writeln('<info>Recalculating tier for customer ID: ' . (int) $customerId . '</info>');
        } else {
            $output->writeln('<info>Recalculating tiers for all customers...</info>');
        }

        try {
            $this->tierRecalculateCron->execute();
            $output->writeln('<info>Tier recalculation completed.</info>');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}

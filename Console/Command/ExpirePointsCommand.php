<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Console\Command;

use Meetanshi\RewardPoints\Cron\ExpirePoints;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to manually run reward points expiration
 */
class ExpirePointsCommand extends Command
{
    /**
     * @param ExpirePoints $expirePointsCron
     */
    public function __construct(
        private readonly ExpirePoints $expirePointsCron,
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
        $this->setName('meetanshi:rewardpoints:expire')
            ->setDescription('Manually run reward points expiration');
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
        $output->writeln('<info>Running reward points expiration...</info>');

        try {
            $this->expirePointsCron->execute();
            $output->writeln('<info>Reward points expiration completed.</info>');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}

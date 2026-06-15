<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Console\Command;

use Meetanshi\RewardPoints\Cron\ExpirationReminders;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to manually send expiration reminder emails
 */
class SendRemindersCommand extends Command
{
    /**
     * @param ExpirationReminders $expirationRemindersCron
     */
    public function __construct(
        private readonly ExpirationReminders $expirationRemindersCron,
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
        $this->setName('meetanshi:rewardpoints:reminders:send')
            ->setDescription('Manually send reward points expiration reminder emails');
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
        $output->writeln('<info>Sending expiration reminder emails...</info>');

        try {
            $this->expirationRemindersCron->execute();
            $output->writeln('<info>Expiration reminders sent.</info>');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}

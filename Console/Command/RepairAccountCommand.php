<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Console\Command;

use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to repair/recompute a customer's reward points balance
 */
class RepairAccountCommand extends Command
{
    private const OPTION_CUSTOMER_ID = 'customer-id';

    /**
     * @param BalanceManagementInterface $balanceManagement
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
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
        $this->setName('meetanshi:rewardpoints:account:repair')
            ->setDescription('Recompute reward points balance from ledger for a customer')
            ->addOption(
                self::OPTION_CUSTOMER_ID,
                null,
                InputOption::VALUE_REQUIRED,
                'Customer ID to repair',
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
        $customerId = (int) $input->getOption(self::OPTION_CUSTOMER_ID);

        if ($customerId <= 0) {
            $output->writeln('<error>--customer-id is required and must be a positive integer.</error>');

            return Command::FAILURE;
        }

        $output->writeln("<info>Repairing account for customer ID: $customerId</info>");

        try {
            $websites = $this->storeManager->getWebsites();

            foreach ($websites as $website) {
                $websiteId = (int) $website->getId();

                try {
                    $accountBefore = $this->accountRepository->getByCustomer($customerId, $websiteId);
                    $oldBalance = $accountBefore->getPointsBalance();

                    $newBalance = $this->balanceManagement->recomputeBalance($customerId, $websiteId);

                    $output->writeln(sprintf(
                        '<info>Website "%s" (ID: %d): Old balance: %d → New balance: %d</info>',
                        $website->getName(),
                        $websiteId,
                        $oldBalance,
                        $newBalance,
                    ));
                } catch (NoSuchEntityException $e) {
                    $output->writeln(
                        "<comment>No account found for customer $customerId on website {$website->getName()}. Skipping.</comment>",
                    );
                }
            }

            return Command::SUCCESS;
        } catch (LocalizedException $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}

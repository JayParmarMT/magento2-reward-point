<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Console\Command;

use Meetanshi\RewardPoints\Model\Indexer\CatalogRuleIndexer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CLI command to rebuild catalog rule product index for Meetanshi reward point rules
 */
class RebuildIndexCommand extends Command
{
    /**
     * @param CatalogRuleIndexer $catalogRuleIndexer
     */
    public function __construct(
        private readonly CatalogRuleIndexer $catalogRuleIndexer,
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
        $this->setName('meetanshi:rewardpoints:rule:rebuild-index')
            ->setDescription('Rebuild catalog rule product index for Meetanshi reward point rules');
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
        $output->writeln('<info>Rebuilding reward points catalog rule product index...</info>');

        try {
            $inserted = $this->catalogRuleIndexer->reindexAll();

            $output->writeln(
                sprintf('<info>Done. %d product-rule index rows written.</info>', $inserted),
            );

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $output->writeln('<error>' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }
    }
}

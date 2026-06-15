<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Meetanshi\RewardPoints\Model\Indexer\CatalogRuleIndexer;
use Psr\Log\LoggerInterface;

/**
 * Nightly cron that rebuilds the catalog-rule product index.
 *
 * This ensures that date-range rule activations/expirations are reflected
 * in the index without manual CLI or admin action.
 */
class CatalogRuleReindex
{
    /**
     * @param CatalogRuleIndexer $catalogRuleIndexer
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CatalogRuleIndexer $catalogRuleIndexer,
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
        try {
            $inserted = $this->catalogRuleIndexer->reindexAll();
            $this->logger->info(
                sprintf('[RewardPoints] CatalogRuleReindex cron: %d rows indexed.', $inserted),
            );
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: CatalogRuleReindex cron failed',
                ['exception' => $e],
            );
        }
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\Indexer\CatalogRuleIndexer;
use Psr\Log\LoggerInterface;

/**
 * Triggers a full catalog-rule product index rebuild when a reward-points catalog rule is saved.
 */
class CatalogRuleSaveAfterObserver implements ObserverInterface
{
    /**
     * @param CatalogRuleIndexer $catalogRuleIndexer
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CatalogRuleIndexer $catalogRuleIndexer,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute observer
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->catalogRuleIndexer->reindexAll();
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: CatalogRuleSaveAfterObserver reindex failed',
                ['exception' => $e],
            );
        }
    }
}

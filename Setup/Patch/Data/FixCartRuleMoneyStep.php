<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Fix existing cart and catalog rules that have NULL or zero money_step.
 *
 * When money_step is NULL for a per_price or per_qty action type, the earning
 * calculator must skip the rule entirely (otherwise it defaults to 1, which
 * massively inflates point awards — e.g. $98 subtotal × 15 pts/step = 1470 pts
 * before the max_points cap).
 *
 * This patch sets money_step = 1.00 as a safe default for any affected rules,
 * so that merchants who had such rules still get a sensible (1 unit = N points)
 * calculation rather than having their rules silently skipped.
 *
 * Merchants should review and update these rules in the admin to set the correct
 * money_step value for their store's base currency.
 */
class FixCartRuleMoneyStep implements DataPatchInterface
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function apply(): self
    {
        $connection = $this->resourceConnection->getConnection();

        // Fix cart rules
        $cartRuleTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_cart_rule');

        $affectedCartRules = $connection->fetchCol(
            $connection->select()
                ->from($cartRuleTable, ['rule_id'])
                ->where('action_type IN (?)', ['per_price', 'per_qty'])
                ->where('money_step IS NULL OR money_step <= 0'),
        );

        if (!empty($affectedCartRules)) {
            $updated = $connection->update(
                $cartRuleTable,
                ['money_step' => 1.0],
                [
                    'action_type IN (?)' => ['per_price', 'per_qty'],
                    'money_step IS NULL OR money_step <= 0',
                ],
            );

            $this->logger->warning(
                'RewardPoints: FixCartRuleMoneyStep patch applied — set money_step=1.00 for rules with NULL/zero money_step. '
                . 'Please review these rules in Admin > Reward Points > Cart Earning Rules and set the correct money_step for your base currency.',
                [
                    'affected_rule_ids' => $affectedCartRules,
                    'rows_updated' => $updated,
                ],
            );
        }

        // Fix catalog rules
        $catalogRuleTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_catalog_rule');

        if ($connection->isTableExists($catalogRuleTable)) {
            $affectedCatalogRules = $connection->fetchCol(
                $connection->select()
                    ->from($catalogRuleTable, ['rule_id'])
                    ->where('action_type IN (?)', ['per_price', 'per_qty'])
                    ->where('money_step IS NULL OR money_step <= 0'),
            );

            if (!empty($affectedCatalogRules)) {
                $connection->update(
                    $catalogRuleTable,
                    ['money_step' => 1.0],
                    [
                        'action_type IN (?)' => ['per_price', 'per_qty'],
                        'money_step IS NULL OR money_step <= 0',
                    ],
                );

                $this->logger->warning(
                    'RewardPoints: FixCartRuleMoneyStep patch applied — set money_step=1.00 for catalog rules with NULL/zero money_step. '
                    . 'Please review these rules in Admin > Reward Points > Catalog Earning Rules.',
                    ['affected_rule_ids' => $affectedCatalogRules],
                );
            }
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [
            CreateDefaultRates::class,
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}

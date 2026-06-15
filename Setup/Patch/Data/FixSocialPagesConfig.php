<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Psr\Log\LoggerInterface;

/**
 * Fix corrupted social_pages multiselect config value.
 *
 * A previous version of this module used Magento\Framework\App\Config\Value as
 * the backend_model for the social_pages multiselect field. That model does not
 * implode PHP arrays before saving, so the admin config save stored the literal
 * string "Array" in core_config_data instead of the expected comma-separated
 * page codes (e.g. "referral,account,product").
 *
 * This patch detects that corrupt value and restores all three pages so that
 * social sharing buttons appear correctly on the My Account dashboard, the
 * Referral page, and product pages.
 */
class FixSocialPagesConfig implements DataPatchInterface
{
    private const CONFIG_PATH = 'meetanshi_rewardpoints/social/social_pages';
    private const CORRECT_VALUE = 'referral,account,product';
    private const CORRUPT_VALUE = 'Array';

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
        $configTable = $this->resourceConnection->getTableName('core_config_data');

        // Find all rows (any scope) where social_pages stored the literal "Array"
        $corruptRows = $connection->fetchAll(
            $connection->select()
                ->from($configTable, ['config_id', 'scope', 'scope_id', 'value'])
                ->where('path = ?', self::CONFIG_PATH)
                ->where('value = ?', self::CORRUPT_VALUE),
        );

        if (empty($corruptRows)) {
            return $this;
        }

        $configIds = array_column($corruptRows, 'config_id');

        $updated = $connection->update(
            $configTable,
            ['value' => self::CORRECT_VALUE],
            ['config_id IN (?)' => $configIds],
        );

        $this->logger->info(
            'RewardPoints: FixSocialPagesConfig patch applied — restored social_pages config from "Array" to "' . self::CORRECT_VALUE . '".',
            [
                'rows_fixed' => $updated,
                'config_ids' => $configIds,
            ],
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * {@inheritdoc}
     */
    public function getAliases(): array
    {
        return [];
    }
}

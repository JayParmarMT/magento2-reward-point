<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Indexer;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Catalog Rule Product Indexer for Meetanshi RewardPoints.
 *
 * Iterates all active catalog earning rules and evaluates their product conditions
 * against every product in every website × customer-group combination, then writes
 * the resulting rows into meetanshi_rewardpoints_catalog_rule_product.
 *
 * Condition evaluation uses a simple attribute-based matcher that supports the
 * serialised condition format produced by the Magento UI component rule editor.
 * Complex conditions (Magento\Rule\Model\Condition\Combine etc.) fall back to
 * matching all products if conditions_serialized is NULL or empty, so that stores
 * using un-conditioned "all-products" rules keep working correctly.
 */
class CatalogRuleIndexer
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param StoreManagerInterface $storeManager
     * @param GroupRepositoryInterface $groupRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param Json $json
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreManagerInterface $storeManager,
        private readonly GroupRepositoryInterface $groupRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly Json $json,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Rebuild the full catalog-rule-product index.
     *
     * @return int Number of index rows written
     */
    public function reindexAll(): int
    {
        $connection = $this->resourceConnection->getConnection();
        $ruleTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_catalog_rule');
        $indexTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_catalog_rule_product');
        $websiteTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_website');
        $cgTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_rule_customer_group');

        // 1. Load all active, date-valid catalog rules
        $today = date('Y-m-d');
        $select = $connection->select()
            ->from($ruleTable)
            ->where('is_active = ?', 1)
            ->where('(from_date IS NULL OR from_date <= ?)', $today)
            ->where('(to_date IS NULL OR to_date >= ?)', $today);

        $rules = $connection->fetchAll($select);

        if (empty($rules)) {
            $connection->truncateTable($indexTable);
            return 0;
        }

        // 2. Collect all websites and customer groups
        $websites = $this->storeManager->getWebsites();

        $groupsCriteria = $this->searchCriteriaBuilder->create();
        $groups = $this->groupRepository->getList($groupsCriteria)->getItems();
        $groupIds = array_map(static fn($g) => (int) $g->getId(), $groups);

        // 3. Load all product IDs with their EAV attribute values for condition evaluation
        $productTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $productIds = $connection->fetchCol(
            $connection->select()->from($productTable, ['entity_id'])->where('type_id IN (?)', ['simple', 'virtual', 'downloadable', 'bundle', 'configurable']),
        );

        if (empty($productIds)) {
            $connection->truncateTable($indexTable);
            return 0;
        }

        // Load product attribute values needed for condition matching
        // We load the type_id and status attributes at minimum
        $productData = $this->loadProductAttributes($productIds);

        // 4. Truncate and rebuild the index
        $connection->truncateTable($indexTable);

        $inserted = 0;
        $batch = [];
        $batchSize = 1000;

        foreach ($rules as $rule) {
            $ruleId = (int) $rule['rule_id'];

            // Resolve which websites this rule applies to
            $ruleWebsiteIds = $this->getRuleWebsiteIds($ruleId, $websiteTable, $connection);

            // Resolve which customer groups this rule applies to
            $ruleGroupIds = $this->getRuleGroupIds($ruleId, $cgTable, $connection);

            // Decode and normalise conditions
            $conditions = $this->decodeConditions((string) ($rule['conditions_serialized'] ?? ''));

            foreach ($websites as $website) {
                $websiteId = (int) $website->getId();

                // Skip if rule is restricted to other websites
                if (!empty($ruleWebsiteIds) && !in_array($websiteId, $ruleWebsiteIds, true)) {
                    continue;
                }

                $applicableGroupIds = empty($ruleGroupIds) ? $groupIds : array_values(
                    array_filter($groupIds, static fn($gid) => in_array($gid, $ruleGroupIds, true)),
                );

                foreach ($productIds as $productId) {
                    $productId = (int) $productId;
                    $product = $productData[$productId] ?? [];

                    if (!$this->matchesConditions($product, $conditions)) {
                        continue;
                    }

                    foreach ($applicableGroupIds as $groupId) {
                        $batch[] = [
                            'rule_id' => $ruleId,
                            'product_id' => $productId,
                            'customer_group_id' => $groupId,
                            'website_id' => $websiteId,
                            'points' => (int) $rule['points'],
                            'action_type' => (string) $rule['action_type'],
                            'money_step' => $rule['money_step'],
                            'max_points' => (int) $rule['max_points'],
                            'priority' => (int) $rule['priority'],
                            'stop_rules_processing' => (int) $rule['stop_rules_processing'],
                        ];

                        if (count($batch) >= $batchSize) {
                            $connection->insertMultiple($indexTable, $batch);
                            $inserted += count($batch);
                            $batch = [];
                        }
                    }
                }
            }
        }

        if (!empty($batch)) {
            $connection->insertMultiple($indexTable, $batch);
            $inserted += count($batch);
        }

        $this->logger->info(
            sprintf('[RewardPoints] CatalogRuleIndexer: wrote %d index rows for %d rules.', $inserted, count($rules)),
        );

        return $inserted;
    }

    /**
     * Load flat product attribute values needed for condition evaluation.
     *
     * We load: entity_id, type_id, status, visibility from the flat/entity table
     * and category assignments from catalog_category_product.
     * This is sufficient for the most common conditions (product type, status,
     * visibility, category). Additional EAV attributes can be added here if needed.
     *
     * @param array<int|string> $productIds
     * @return array<int, array<string, mixed>>  keyed by product_id
     */
    private function loadProductAttributes(array $productIds): array
    {
        $connection = $this->resourceConnection->getConnection();
        $entityTable = $this->resourceConnection->getTableName('catalog_product_entity');
        $statusTable = $this->resourceConnection->getTableName('catalog_product_entity_int');
        $catProductTable = $this->resourceConnection->getTableName('catalog_category_product');
        $eavAttributeTable = $this->resourceConnection->getTableName('eav_attribute');

        // Get attribute IDs for status and visibility
        $attrIds = $connection->fetchPairs(
            $connection->select()
                ->from($eavAttributeTable, ['attribute_code', 'attribute_id'])
                ->where('entity_type_id = ?', 4) // catalog_product
                ->where('attribute_code IN (?)', ['status', 'visibility']),
        );

        $statusAttrId = (int) ($attrIds['status'] ?? 0);
        $visibilityAttrId = (int) ($attrIds['visibility'] ?? 0);

        // Load base product rows
        $rows = $connection->fetchAll(
            $connection->select()
                ->from($entityTable, ['entity_id', 'type_id', 'sku'])
                ->where('entity_id IN (?)', $productIds),
        );

        $data = [];

        foreach ($rows as $row) {
            $data[(int) $row['entity_id']] = [
                'entity_id' => (int) $row['entity_id'],
                'type_id' => $row['type_id'],
                'sku' => $row['sku'],
                'status' => 1,
                'visibility' => 4,
                'category_ids' => [],
            ];
        }

        // Load status values (store 0 = global scope)
        if ($statusAttrId > 0) {
            $statusRows = $connection->fetchAll(
                $connection->select()
                    ->from($statusTable, ['entity_id', 'value'])
                    ->where('attribute_id = ?', $statusAttrId)
                    ->where('store_id = ?', 0)
                    ->where('entity_id IN (?)', $productIds),
            );

            foreach ($statusRows as $row) {
                if (isset($data[(int) $row['entity_id']])) {
                    $data[(int) $row['entity_id']]['status'] = (int) $row['value'];
                }
            }
        }

        // Load visibility values
        if ($visibilityAttrId > 0) {
            $visRows = $connection->fetchAll(
                $connection->select()
                    ->from($statusTable, ['entity_id', 'value'])
                    ->where('attribute_id = ?', $visibilityAttrId)
                    ->where('store_id = ?', 0)
                    ->where('entity_id IN (?)', $productIds),
            );

            foreach ($visRows as $row) {
                if (isset($data[(int) $row['entity_id']])) {
                    $data[(int) $row['entity_id']]['visibility'] = (int) $row['value'];
                }
            }
        }

        // Load category assignments
        $catRows = $connection->fetchAll(
            $connection->select()
                ->from($catProductTable, ['product_id', 'category_id'])
                ->where('product_id IN (?)', $productIds),
        );

        foreach ($catRows as $row) {
            $pid = (int) $row['product_id'];

            if (isset($data[$pid])) {
                $data[$pid]['category_ids'][] = (int) $row['category_id'];
            }
        }

        return $data;
    }

    /**
     * Decode conditions from a serialised JSON string.
     *
     * Returns an array of condition arrays, or [] if there are no conditions
     * (meaning the rule applies to all products).
     *
     * @param string $serialized
     * @return array<int, array<string, mixed>>
     */
    private function decodeConditions(string $serialized): array
    {
        if (empty($serialized)) {
            return [];
        }

        try {
            $data = $this->json->unserialize($serialized);

            if (!is_array($data)) {
                return [];
            }

            // Support two formats:
            // 1. Flat: [['attribute'=>'...', 'operator'=>'...', 'value'=>'...'], ...]
            // 2. Magento combine: {'type':'...Combine...', 'conditions':[...]}
            if (isset($data['conditions']) && is_array($data['conditions'])) {
                return $data['conditions'];
            }

            if (isset($data[0]) && is_array($data[0])) {
                return $data;
            }
        } catch (\Exception $e) {
            $this->logger->warning(
                '[RewardPoints] CatalogRuleIndexer: could not decode conditions',
                ['error' => $e->getMessage()],
            );
        }

        return [];
    }

    /**
     * Test whether a product matches a set of decoded conditions.
     *
     * When $conditions is empty the rule applies to all products and we return true.
     * All conditions in the array must match (implicit AND — same as Magento rule default).
     *
     * Supported condition attributes: type_id, status, visibility, category_ids (any/all).
     * Unknown attributes are skipped (assumed to match).
     *
     * @param array<string, mixed> $product
     * @param array<int, array<string, mixed>> $conditions
     * @return bool
     */
    private function matchesConditions(array $product, array $conditions): bool
    {
        if (empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!is_array($condition)) {
                continue;
            }

            $attribute = (string) ($condition['attribute'] ?? $condition['attribute_code'] ?? '');
            $operator = (string) ($condition['operator'] ?? '==');
            $condValue = $condition['value'] ?? null;

            if ($attribute === '') {
                continue;
            }

            // Resolve product value
            if ($attribute === 'category_ids') {
                $productValue = $product['category_ids'] ?? [];

                if (!$this->evaluateCategoryCondition($productValue, $operator, $condValue)) {
                    return false;
                }

                continue;
            }

            $productValue = $product[$attribute] ?? null;

            if ($productValue === null) {
                // Unknown attribute — skip (assume match)
                continue;
            }

            if (!$this->evaluateCondition($productValue, $operator, $condValue)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a scalar condition.
     *
     * @param mixed $productValue
     * @param string $operator
     * @param mixed $condValue
     * @return bool
     */
    private function evaluateCondition(mixed $productValue, string $operator, mixed $condValue): bool
    {
        return match ($operator) {
            '==' , '='  => $productValue == $condValue,
            '!=' , '<>' => $productValue != $condValue,
            '>='        => $productValue >= $condValue,
            '<='        => $productValue <= $condValue,
            '>'         => $productValue > $condValue,
            '<'         => $productValue < $condValue,
            default     => true,
        };
    }

    /**
     * Evaluate a category-IDs condition (supports () and !() operators).
     *
     * @param array<int> $productCategoryIds
     * @param string $operator
     * @param mixed $condValue
     * @return bool
     */
    private function evaluateCategoryCondition(array $productCategoryIds, string $operator, mixed $condValue): bool
    {
        $values = is_array($condValue)
            ? array_map('intval', $condValue)
            : array_map('intval', explode(',', (string) $condValue));

        $intersect = array_intersect($productCategoryIds, $values);

        return match ($operator) {
            '()'  => !empty($intersect),  // product is in any of the listed categories
            '!()' => empty($intersect),   // product is NOT in any of the listed categories
            '=='  => !empty($intersect),
            '!='  => empty($intersect),
            default => true,
        };
    }

    /**
     * Get website IDs associated with a catalog rule (empty = all websites).
     *
     * @param int $ruleId
     * @param string $websiteTable
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return int[]
     */
    private function getRuleWebsiteIds(
        int $ruleId,
        string $websiteTable,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
    ): array {
        $rows = $connection->fetchCol(
            $connection->select()
                ->from($websiteTable, ['website_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', 'catalog_earning'),
        );

        return array_map('intval', $rows);
    }

    /**
     * Get customer group IDs associated with a catalog rule (empty = all groups).
     *
     * @param int $ruleId
     * @param string $cgTable
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @return int[]
     */
    private function getRuleGroupIds(
        int $ruleId,
        string $cgTable,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
    ): array {
        $rows = $connection->fetchCol(
            $connection->select()
                ->from($cgTable, ['customer_group_id'])
                ->where('rule_id = ?', $ruleId)
                ->where('rule_type = ?', 'catalog_earning'),
        );

        return array_map('intval', $rows);
    }
}

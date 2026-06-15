<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Tier\Grid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier\Collection as TierCollection;
use Psr\Log\LoggerInterface;
use Magento\Framework\DB\Expr as Zend_Db_Expr;

/**
 * Tier Grid Collection
 *
 * Extends the base tier collection and joins websites and customer groups
 * for the admin grid display and filtering.
 */
class Collection extends TierCollection implements SearchResultInterface
{
    /**
     * @var AggregationInterface
     */
    private AggregationInterface $aggregations;

    /**
     * @param EntityFactoryInterface $entityFactory
     * @param LoggerInterface $logger
     * @param FetchStrategyInterface $fetchStrategy
     * @param ManagerInterface $eventManager
     * @param mixed $mainTable
     * @param mixed $eventPrefix
     * @param mixed $eventObject
     * @param mixed $resourceModel
     * @param string $model
     * @param mixed $connection
     * @param AbstractDb|null $resource
     */
    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        mixed $mainTable,
        mixed $eventPrefix,
        mixed $eventObject,
        mixed $resourceModel,
        /**
         * mixed.
         */
        
        string $model = Document::class,
        mixed $connection = null,
        ?AbstractDb $resource = null,
    ) {
        parent::__construct(
            $entityFactory,
            $logger,
            $fetchStrategy,
            $eventManager,
            $connection,
            $resource,
        );

        $this->_eventPrefix = $eventPrefix;
        $this->_eventObject = $eventObject;
        $this->_init($model, $resourceModel);
        $this->setMainTable($mainTable);
    }

    /**
     * Get aggregations
     *
     * @return AggregationInterface
     */
    public function getAggregations(): AggregationInterface
    {
        return $this->aggregations;
    }

    /**
     * Set aggregations
     *
     * @param AggregationInterface $aggregations
     * @return $this
     */
    public function setAggregations($aggregations): static
    {
        $this->aggregations = $aggregations;

        return $this;
    }

    /**
     * Get search criteria
     *
     * @return SearchCriteriaInterface|null
     */
    public function getSearchCriteria(): ?SearchCriteriaInterface
    {
        return null;
    }

    /**
     * Set search criteria
     *
     * @param SearchCriteriaInterface|null $searchCriteria
     * @return $this
     */
    public function setSearchCriteria(?SearchCriteriaInterface $searchCriteria = null): static
    {
        return $this;
    }

    /**
     * Get total count
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->getSize();
    }

    /**
     * Set total count
     *
     * @param int $totalCount
     * @return $this
     */
    public function setTotalCount($totalCount): static
    {
        return $this;
    }

    /**
     * Set items
     *
     * @param array|null $items
     * @return $this
     */
    public function setItems(?array $items = null): static
    {
        return $this;
    }

    /**
     * Initialize select with filterMap for ambiguous columns and website/group joins.
     *
     * @return void
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->_setIdFieldName('tier_id');
        $this->addFilterToMap('tier_id', 'main_table.tier_id');
        $this->addFilterToMap('websites', 'rw_agg.websites');
        $this->addFilterToMap('customer_groups', 'rg_agg.customer_groups');
        $this->joinWebsitesAndGroups();
    }

    /**
     * Join websites and customer groups via GROUP_CONCAT subqueries.
     *
     * @return void
     */
    private function joinWebsitesAndGroups(): void
    {
        $connection = $this->getConnection();
        $ruleType   = 'tier';

        $websiteSubquery = $connection->select()
            ->from(
                ['rw' => $this->getTable('meetanshi_rewardpoints_rule_website')],
                ['rule_id'],
            )
            ->joinInner(
                ['sw' => $this->getTable('store_website')],
                'sw.website_id = rw.website_id AND sw.website_id != 0',
                ['websites' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT sw.name ORDER BY sw.name SEPARATOR ', ')")],
            )
            ->where('rw.rule_type = ?', $ruleType)
            ->group('rw.rule_id');

        $groupSubquery = $connection->select()
            ->from(
                ['rg' => $this->getTable('meetanshi_rewardpoints_rule_customer_group')],
                ['rule_id'],
            )
            ->joinInner(
                ['cg' => $this->getTable('customer_group')],
                'cg.customer_group_id = rg.customer_group_id',
                ['customer_groups' => new Zend_Db_Expr("GROUP_CONCAT(DISTINCT cg.customer_group_code ORDER BY cg.customer_group_code SEPARATOR ', ')")],
            )
            ->where('rg.rule_type = ?', $ruleType)
            ->group('rg.rule_id');

        $this->getSelect()
            ->joinLeft(
                ['rw_agg' => $websiteSubquery],
                'rw_agg.rule_id = main_table.tier_id',
                ['websites'],
            )
            ->joinLeft(
                ['rg_agg' => $groupSubquery],
                'rg_agg.rule_id = main_table.tier_id',
                ['customer_groups'],
            );
    }

    /**
     * Handle websites/customer_groups filters via EXISTS subqueries.
     *
     * GROUP_CONCAT alias columns cannot be filtered in WHERE. Use correlated
     * EXISTS subqueries against the junction tables directly.
     *
     * @param string|array $field
     * @param array|string|null $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null)
    {
        if ($field === 'websites') {
            $value = is_array($condition) ? reset($condition) : $condition;
            $websiteSelect = $this->getConnection()->select()
                ->from(
                    ['fw' => $this->getTable('meetanshi_rewardpoints_rule_website')],
                    ['rule_id'],
                )
                ->join(
                    ['fsw' => $this->getTable('store_website')],
                    'fsw.website_id = fw.website_id',
                    [],
                )
                ->where('fw.rule_type = ?', 'tier')
                ->where('fw.rule_id = main_table.tier_id')
                ->where('fsw.name LIKE ?', '%' . $value . '%');
            $this->getSelect()->where('EXISTS (?)', new Zend_Db_Expr($websiteSelect));

            return $this;
        }

        if ($field === 'customer_groups') {
            $value = is_array($condition) ? reset($condition) : $condition;
            $groupSelect = $this->getConnection()->select()
                ->from(
                    ['fg' => $this->getTable('meetanshi_rewardpoints_rule_customer_group')],
                    ['rule_id'],
                )
                ->join(
                    ['fcg' => $this->getTable('customer_group')],
                    'fcg.customer_group_id = fg.customer_group_id',
                    [],
                )
                ->where('fg.rule_type = ?', 'tier')
                ->where('fg.rule_id = main_table.tier_id')
                ->where('fcg.customer_group_code LIKE ?', '%' . $value . '%');
            $this->getSelect()->where('EXISTS (?)', new Zend_Db_Expr($groupSelect));

            return $this;
        }

        return parent::addFieldToFilter($field, $condition);
    }
}

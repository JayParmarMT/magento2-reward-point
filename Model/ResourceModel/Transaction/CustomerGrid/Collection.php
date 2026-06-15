<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CustomerGrid;

use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\AbstractDb;
use Magento\Framework\View\Element\UiComponent\DataProvider\Document;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\Collection as TransactionCollection;
use Psr\Log\LoggerInterface;

/**
 * Customer-scoped Transaction Grid Collection
 *
 * Extends the base transaction collection and filters by customer_id
 * derived from the current admin request (customer edit page).
 * Used by the embedded transaction grid in the Customer Edit Reward Points tab.
 */
class Collection extends TransactionCollection implements SearchResultInterface
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
     * Initialise select: set the id field name so EditAction and the grid work correctly.
     *
     * Customer scoping is applied via addFieldToFilter('id', ...) which the DataProvider
     * injects through filter_url_params / SearchCriteria. We remap 'id' → customer_id
     * in addFieldToFilter() below.
     *
     * @return void
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->_setIdFieldName('transaction_id');
    }

    /**
     * Remap the synthetic 'id' field (injected by filter_url_params) to the real
     * customer_id column so the collection filters correctly.
     *
     * The framework's filterPool calls addFieldToFilter('id', ['eq' => <customerId>])
     * when filter_url_params maps 'id' => '*'. Without this override the query would
     * try to filter on a column named 'id' which does not exist in the transaction table.
     *
     * @param array|string $field
     * @param mixed $condition
     * @return $this
     */
    public function addFieldToFilter($field, $condition = null): static
    {
        if ($field === 'id') {
            $field = 'main_table.customer_id';
        }

        return parent::addFieldToFilter($field, $condition);
    }
}

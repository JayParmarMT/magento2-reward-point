<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Transaction\Grid;

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
 * Transaction Grid Collection
 *
 * Extends the base transaction collection and joins customer_entity
 * to provide customer_name for the admin grid display.
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
     * Join customer name after collection is loaded
     *
     * @return void
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->joinCustomerName();
    }

    /**
     * Join customer_entity to get customer name and email
     *
     * @return void
     */
    private function joinCustomerName(): void
    {
        $this->getSelect()->joinLeft(
            ['ce' => $this->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            [
                'customer_name' => new \Magento\Framework\DB\Expr("CONCAT(COALESCE(ce.firstname, ''), ' ', COALESCE(ce.lastname, ''))"),
                'customer_email' => 'ce.email',
            ],
        );
    }

}

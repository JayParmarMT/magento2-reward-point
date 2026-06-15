<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\Tier;

use Magento\Framework\App\RequestInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\Account\CollectionFactory as AccountCollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Account\Collection as AccountCollection;
use Magento\Framework\DB\Expr as Zend_Db_Expr;

/**
 * Data provider for the Tier Customer Listing embedded in the Tier edit form.
 * Supports pagination, sorting, and column filtering.
 */
class CustomerListingDataProvider extends AbstractDataProvider
{
    /**
     * @var bool
     */
    private bool $collectionPrepared = false;

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param AccountCollectionFactory $collectionFactory
     * @param RequestInterface $request
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        private readonly AccountCollectionFactory $collectionFactory,
        private readonly RequestInterface $request,
        array $meta = [],
        array $data = [],
    ) {
        $this->collection = $collectionFactory->create();
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
    }

    /**
     * Resolve tier_id from multiple possible request sources
     *
     * @return int
     */
    private function resolveTierId(): int
    {
        $tierId = (int) $this->request->getParam('tier_id');

        if ($tierId) {
            return $tierId;
        }

        $params = $this->request->getParam('params', []);

        if (!empty($params['tier_id'])) {
            return (int) $params['tier_id'];
        }

        $filters = $this->request->getParam('filters', []);

        if (!empty($filters['tier_id'])) {
            return (int) $filters['tier_id'];
        }

        return 0;
    }

    /**
     * Prepare the collection once: join, tier filter, column filters, sorting, paging
     *
     * @return AccountCollection
     */
    private function prepareCollection(): AccountCollection
    {
        if ($this->collectionPrepared) {
            return $this->collection;
        }

        $this->collectionPrepared = true;

        /** @var AccountCollection $collection */
        $collection = $this->collection;

        // Join customer name and email from customer_entity
        $collection->getSelect()->joinLeft(
            ['ce' => $collection->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            [
                'customer_name' => new Zend_Db_Expr(
                    "TRIM(CONCAT(COALESCE(ce.firstname, ''), ' ', COALESCE(ce.lastname, '')))",
                ),
                'customer_email' => 'ce.email',
            ],
        );

        // Always filter by tier
        $tierId = $this->resolveTierId();

        if ($tierId) {
            $collection->addFieldToFilter('main_table.current_tier_id', $tierId);
        } else {
            $collection->addFieldToFilter('main_table.account_id', ['null' => true]);
        }

        // Apply column filters from the grid toolbar — only allow known columns
        $allowedFilters = ['account_id', 'customer_id', 'customer_name', 'customer_email', 'points_balance'];
        $filters = $this->request->getParam('filters', []);

        foreach ($filters as $field => $value) {
            if (!in_array($field, $allowedFilters, true) || $value === '' || $value === null) {
                continue;
            }

            $this->applyFilter($collection, $field, $value);
        }

        // Apply fulltext search (maps to customer_name / customer_email)
        $search = $this->request->getParam('search', '');

        if (!empty($search)) {
            $collection->getSelect()->where(
                "TRIM(CONCAT(COALESCE(ce.firstname, ''), ' ', COALESCE(ce.lastname, ''))) LIKE ?"
                . " OR ce.email LIKE ?",
                '%' . $search . '%',
            );
        }

        // Apply sorting
        $sortField = $this->request->getParam('sortField', 'account_id');
        $sortDir   = strtoupper((string) $this->request->getParam('sortDir', 'asc')) === 'DESC' ? 'DESC' : 'ASC';

        $sortColumn = match ($sortField) {
            'customer_name'  => 'customer_name',
            'customer_email' => 'customer_email',
            'customer_id'    => 'main_table.customer_id',
            'points_balance' => 'main_table.points_balance',
            default          => 'main_table.account_id',
        };

        $collection->getSelect()->order("{$sortColumn} {$sortDir}");

        // Apply pagination
        $paging      = $this->request->getParam('paging', []);
        $pageSize    = isset($paging['pageSize']) ? (int) $paging['pageSize'] : 20;
        $pageCurrent = isset($paging['current']) ? (int) $paging['current'] : 1;

        if ($pageSize <= 0) {
            $pageSize = 20;
        }

        if ($pageCurrent <= 0) {
            $pageCurrent = 1;
        }

        $collection->setPageSize($pageSize);
        $collection->setCurPage($pageCurrent);

        return $collection;
    }

    /**
     * Apply a single filter value to the collection
     *
     * @param AccountCollection $collection
     * @param string $field
     * @param mixed $value
     * @return void
     */
    private function applyFilter(AccountCollection $collection, string $field, mixed $value): void
    {
        // Range filters arrive as ['from' => ..., 'to' => ...]
        if (is_array($value)) {
            if (isset($value['from']) && $value['from'] !== '') {
                $collection->addFieldToFilter(
                    $this->resolveFilterField($field),
                    ['gteq' => $value['from']],
                );
            }

            if (isset($value['to']) && $value['to'] !== '') {
                $collection->addFieldToFilter(
                    $this->resolveFilterField($field),
                    ['lteq' => $value['to']],
                );
            }

            return;
        }

        // Text filters on joined columns need LIKE via getSelect()->where()
        if (in_array($field, ['customer_name', 'customer_email'], true)) {
            $dbField = $field === 'customer_name'
                ? "TRIM(CONCAT(COALESCE(ce.firstname, ''), ' ', COALESCE(ce.lastname, '')))"
                : 'ce.email';
            $collection->getSelect()->where("{$dbField} LIKE ?", '%' . $value . '%');

            return;
        }

        $collection->addFieldToFilter(
            $this->resolveFilterField($field),
            ['like' => '%' . $value . '%'],
        );
    }

    /**
     * Map grid field name to the correct SQL column reference
     *
     * @param string $field
     * @return string
     */
    private function resolveFilterField(string $field): string
    {
        return match ($field) {
            'customer_id'    => 'main_table.customer_id',
            'points_balance' => 'main_table.points_balance',
            'account_id'     => 'main_table.account_id',
            default          => $field,
        };
    }

    /**
     * Get data for the grid
     *
     * @return array
     */
    public function getData(): array
    {
        $collection = $this->prepareCollection();

        $items = [];

        foreach ($collection as $item) {
            $items[] = $item->getData();
        }

        return [
            'totalRecords' => $collection->getSize(),
            'items'        => $items,
        ];
    }
}

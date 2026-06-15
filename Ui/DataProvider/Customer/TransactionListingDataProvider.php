<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\Customer;

use Magento\Framework\Api\FilterBuilder;
use Magento\Framework\Api\Search\ReportingInterface;
use Magento\Framework\Api\Search\SearchCriteriaBuilder;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider;

/**
 * Data provider for the customer-scoped transaction listing
 * embedded in the Customer Edit Reward Points tab.
 *
 * Injects `filter_url_params` at construction time so that:
 *  1. The DataProvider appends /id/<customerId>/ to every mui/index/render call
 *     (the JS grid provider picks this up from update_url).
 *  2. An eq filter for customer_id is added to the SearchCriteria so the
 *     collection's addFieldToFilter('id', ...) override scopes results correctly.
 */
class TransactionListingDataProvider extends DataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param ReportingInterface $reporting
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param RequestInterface $request
     * @param FilterBuilder $filterBuilder
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        ReportingInterface $reporting,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        RequestInterface $request,
        FilterBuilder $filterBuilder,
        array $meta = [],
        array $data = [],
    ) {
        // Inject filter_url_params so prepareUpdateUrl() (called in parent::__construct)
        // reads 'id' from the current request and appends it to every mui/index/render URL.
        $data['config']['filter_url_params']['id'] = '*';

        parent::__construct(
            $name,
            $primaryFieldName,
            $requestFieldName,
            $reporting,
            $searchCriteriaBuilder,
            $request,
            $filterBuilder,
            $meta,
            $data,
        );
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\Transaction;

use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory;

/**
 * Transaction Add Form Data Provider
 */
class AddFormDataProvider extends AbstractDataProvider
{
    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        array $meta = [],
        array $data = [],
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    /**
     * Get data
     *
     * @return array
     */
    public function getData(): array
    {
        return [
            '' => [
                'customer_id' => '',
                'points' => '',
                'comment' => '',
                'expire_after_days' => 0,
                'notify_customer' => 0,
            ],
        ];
    }
}

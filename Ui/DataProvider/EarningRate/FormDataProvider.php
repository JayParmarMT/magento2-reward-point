<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\EarningRate;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\RuleJunction;

/**
 * Earning Rate Form Data Provider
 */
class FormDataProvider extends AbstractDataProvider
{
    private const RULE_TYPE = 'earning_rate';

    /**
     * @var array
     */
    private array $loadedData = [];

    /**
     * @param string $name
     * @param string $primaryFieldName
     * @param string $requestFieldName
     * @param CollectionFactory $collectionFactory
     * @param DataPersistorInterface $dataPersistor
     * @param RuleJunction $ruleJunction
     * @param array $meta
     * @param array $data
     */
    public function __construct(
        string $name,
        string $primaryFieldName,
        string $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly RuleJunction $ruleJunction,
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
        if (!empty($this->loadedData)) {
            return $this->loadedData;
        }

        $items = $this->collection->getItems();

        foreach ($items as $model) {
            $rateId = (int) $model->getId();
            $data = $model->getData();
            $data['website_ids'] = array_map('strval', $this->ruleJunction->getWebsiteIds($rateId, self::RULE_TYPE));
            $data['customer_group_ids'] = array_map('strval', $this->ruleJunction->getCustomerGroupIds($rateId, self::RULE_TYPE));
            $this->loadedData[$rateId] = $data;
        }

        $persistedData = $this->dataPersistor->get('meetanshi_rewardpoints_earning_rate');

        if (!empty($persistedData)) {
            $rateId = isset($persistedData['rate_id']) ? (int) $persistedData['rate_id'] : null;
            $key = $rateId ?? 'new';
            $this->loadedData[$key] = $persistedData;
            $this->dataPersistor->clear('meetanshi_rewardpoints_earning_rate');
        }

        return $this->loadedData;
    }
}

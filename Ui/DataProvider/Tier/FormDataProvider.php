<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\Tier;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\RuleJunction;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier\CollectionFactory;

/**
 * UI DataProvider for Tier Form
 */
class FormDataProvider extends AbstractDataProvider
{
    private const RULE_TYPE = 'tier';

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

        foreach ($items as $tier) {
            $tierId   = (int) $tier->getId();
            $tierData = $tier->getData();

            if (!empty($tierData['image'])) {
                $tierData['image'] = [
                    [
                        'name' => $tierData['image'],
                        'url'  => '/media/meetanshi/rewardpoints/tier/' . $tierData['image'],
                    ],
                ];
            }

            $tierData['website_ids']        = array_map('strval', $this->ruleJunction->getWebsiteIds($tierId, self::RULE_TYPE));
            $tierData['customer_group_ids'] = array_map('strval', $this->ruleJunction->getCustomerGroupIds($tierId, self::RULE_TYPE));

            $this->loadedData[$tierId] = $tierData;
        }

        $persistedData = $this->dataPersistor->get('meetanshi_rewardpoints_tier');

        if (!empty($persistedData)) {
            $tierId = isset($persistedData['tier_id']) ? (int) $persistedData['tier_id'] : null;
            $key    = $tierId ?? 'new';
            $this->loadedData[$key] = $persistedData;
            $this->dataPersistor->clear('meetanshi_rewardpoints_tier');
        }

        return $this->loadedData;
    }
}

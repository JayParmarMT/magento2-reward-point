<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\DataProvider\ReferralRule;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Ui\DataProvider\AbstractDataProvider;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\ReferralRule\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\RuleJunction;

/**
 * UI DataProvider for Referral Rule Form
 */
class FormDataProvider extends AbstractDataProvider
{
    private const RULE_TYPE = 'referral';

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

        foreach ($items as $rule) {
            $ruleId = (int) $rule->getId();
            $data = $rule->getData();
            $data['website_ids'] = array_map('strval', $this->ruleJunction->getWebsiteIds($ruleId, self::RULE_TYPE));
            $data['customer_group_ids'] = array_map('strval', $this->ruleJunction->getCustomerGroupIds($ruleId, self::RULE_TYPE));
            $this->loadedData[$ruleId] = $data;
        }

        if (empty($this->loadedData)) {
            $this->loadedData[''] = [
                'discount_type' => 'fixed',
            ];
        }

        $persistedData = $this->dataPersistor->get('meetanshi_rewardpoints_referral_rule');

        if (!empty($persistedData)) {
            $ruleId = isset($persistedData['rule_id']) ? (int) $persistedData['rule_id'] : null;
            $key = $ruleId ?? 'new';
            $this->loadedData[$key] = $persistedData;
            $this->dataPersistor->clear('meetanshi_rewardpoints_referral_rule');
        }

        return $this->loadedData;
    }
}

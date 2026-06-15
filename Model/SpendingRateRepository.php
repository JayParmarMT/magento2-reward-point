<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Api\SearchResultsInterfaceFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\SpendingRateInterface;
use Meetanshi\RewardPoints\Api\SpendingRateRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate as SpendingRateResource;
use Meetanshi\RewardPoints\Model\ResourceModel\SpendingRate\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\RuleJunction;

/**
 * Spending Rate Repository Implementation
 */
class SpendingRateRepository implements SpendingRateRepositoryInterface
{
    private const RULE_TYPE = 'spending_rate';

    /**
     * @param SpendingRateResource $resource
     * @param SpendingRateFactory $spendingRateFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param RuleJunction $ruleJunction
     */
    public function __construct(
        private readonly SpendingRateResource $resource,
        private readonly SpendingRateFactory $spendingRateFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly RuleJunction $ruleJunction,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(
        SpendingRateInterface $spendingRate,
        array $websiteIds,
        array $customerGroupIds,
    ): SpendingRateInterface {
        try {
            $this->resource->save($spendingRate);
            $rateId = (int) $spendingRate->getRateId();
            $this->ruleJunction->saveWebsites($rateId, self::RULE_TYPE, $websiteIds);
            $this->ruleJunction->saveCustomerGroups($rateId, self::RULE_TYPE, $customerGroupIds);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save spending rate: %1', $e->getMessage()),
                $e,
            );
        }

        return $spendingRate;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $rateId): SpendingRateInterface
    {
        $spendingRate = $this->spendingRateFactory->create();
        $this->resource->load($spendingRate, $rateId);

        if (!$spendingRate->getRateId()) {
            throw new NoSuchEntityException(
                __('Spending rate with ID "%1" does not exist.', $rateId),
            );
        }

        $spendingRate->setData(
            'website_ids',
            $this->ruleJunction->getWebsiteIds($rateId, self::RULE_TYPE),
        );
        $spendingRate->setData(
            'customer_group_ids',
            $this->ruleJunction->getCustomerGroupIds($rateId, self::RULE_TYPE),
        );

        return $spendingRate;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(SpendingRateInterface $spendingRate): bool
    {
        try {
            $rateId = (int) $spendingRate->getRateId();
            $this->ruleJunction->deleteByRule($rateId, self::RULE_TYPE);
            $this->resource->delete($spendingRate);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete spending rate: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById(int $rateId): bool
    {
        return $this->delete($this->getById($rateId));
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }
}

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
use Meetanshi\RewardPoints\Api\Data\EarningRateInterface;
use Meetanshi\RewardPoints\Api\EarningRateRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate as EarningRateResource;
use Meetanshi\RewardPoints\Model\ResourceModel\EarningRate\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\RuleJunction;

/**
 * Earning Rate Repository Implementation
 */
class EarningRateRepository implements EarningRateRepositoryInterface
{
    private const RULE_TYPE = 'earning_rate';

    /**
     * @param EarningRateResource $resource
     * @param EarningRateFactory $earningRateFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param RuleJunction $ruleJunction
     */
    public function __construct(
        private readonly EarningRateResource $resource,
        private readonly EarningRateFactory $earningRateFactory,
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
        EarningRateInterface $earningRate,
        array $websiteIds,
        array $customerGroupIds,
    ): EarningRateInterface {
        try {
            $this->resource->save($earningRate);
            $rateId = (int) $earningRate->getRateId();
            $this->ruleJunction->saveWebsites($rateId, self::RULE_TYPE, $websiteIds);
            $this->ruleJunction->saveCustomerGroups($rateId, self::RULE_TYPE, $customerGroupIds);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save earning rate: %1', $e->getMessage()),
                $e,
            );
        }

        return $earningRate;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $rateId): EarningRateInterface
    {
        $earningRate = $this->earningRateFactory->create();
        $this->resource->load($earningRate, $rateId);

        if (!$earningRate->getRateId()) {
            throw new NoSuchEntityException(
                __('Earning rate with ID "%1" does not exist.', $rateId),
            );
        }

        $earningRate->setData(
            'website_ids',
            $this->ruleJunction->getWebsiteIds($rateId, self::RULE_TYPE),
        );
        $earningRate->setData(
            'customer_group_ids',
            $this->ruleJunction->getCustomerGroupIds($rateId, self::RULE_TYPE),
        );

        return $earningRate;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(EarningRateInterface $earningRate): bool
    {
        try {
            $rateId = (int) $earningRate->getRateId();
            $this->ruleJunction->deleteByRule($rateId, self::RULE_TYPE);
            $this->resource->delete($earningRate);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete earning rate: %1', $e->getMessage()),
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

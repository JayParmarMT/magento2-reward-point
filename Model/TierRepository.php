<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TierInterface;
use Meetanshi\RewardPoints\Api\Data\TierSearchResultsInterface;
use Meetanshi\RewardPoints\Api\Data\TierSearchResultsInterfaceFactory;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier as TierResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Tier\CollectionFactory;

/**
 * Tier Repository Implementation
 */
class TierRepository implements TierRepositoryInterface
{
    /**
     * @param TierResource $resource
     * @param TierFactory $tierFactory
     * @param CollectionFactory $collectionFactory
     * @param TierSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly TierResource $resource,
        private readonly TierFactory $tierFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly TierSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(TierInterface $tier): TierInterface
    {
        try {
            $this->resource->save($tier);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save tier: %1', $e->getMessage()),
                $e,
            );
        }

        return $tier;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $tierId): TierInterface
    {
        $tier = $this->tierFactory->create();
        $this->resource->load($tier, $tierId);

        if (!$tier->getTierId()) {
            throw new NoSuchEntityException(
                __('Tier with ID "%1" does not exist.', $tierId),
            );
        }

        return $tier;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TierInterface $tier): bool
    {
        try {
            $this->resource->delete($tier);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete tier: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById(int $tierId): bool
    {
        return $this->delete($this->getById($tierId));
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TierSearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $this->collectionProcessor->process($searchCriteria, $collection);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setSearchCriteria($searchCriteria);
        $searchResults->setItems($collection->getItems());
        $searchResults->setTotalCount($collection->getSize());

        return $searchResults;
    }

    /**
     * {@inheritdoc}
     */
    public function getActiveByMinPoints(int $points): array
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter(TierInterface::IS_ACTIVE, ['eq' => 1]);
        $collection->addFieldToFilter(TierInterface::MIN_POINTS, ['lteq' => $points]);
        $collection->setOrder(TierInterface::MIN_POINTS, 'DESC');

        return $collection->getItems();
    }
}

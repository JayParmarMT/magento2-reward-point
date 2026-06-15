<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionSearchResultsInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionSearchResultsInterfaceFactory;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction as TransactionResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory;

/**
 * Transaction Repository Implementation
 */
class TransactionRepository implements TransactionRepositoryInterface
{
    /**
     * @param TransactionResource $resource
     * @param TransactionFactory $transactionFactory
     * @param CollectionFactory $collectionFactory
     * @param TransactionSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     */
    public function __construct(
        private readonly TransactionResource $resource,
        private readonly TransactionFactory $transactionFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly TransactionSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(TransactionInterface $transaction): TransactionInterface
    {
        try {
            $this->resource->save($transaction);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save reward points transaction: %1', $e->getMessage()),
                $e,
            );
        }

        return $transaction;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $transactionId): TransactionInterface
    {
        $transaction = $this->transactionFactory->create();
        $this->resource->load($transaction, $transactionId);

        if (!$transaction->getTransactionId()) {
            throw new NoSuchEntityException(
                __('Reward points transaction with ID "%1" does not exist.', $transactionId),
            );
        }

        return $transaction;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(TransactionInterface $transaction): bool
    {
        try {
            $this->resource->delete($transaction);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete reward points transaction: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria): TransactionSearchResultsInterface
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

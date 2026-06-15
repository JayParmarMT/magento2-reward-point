<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Api\Data\AccountSearchResultsInterface;
use Meetanshi\RewardPoints\Api\Data\AccountSearchResultsInterfaceFactory;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Model\AccountFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Account as AccountResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Account\CollectionFactory;

/**
 * Account Repository Implementation
 */
class AccountRepository implements AccountRepositoryInterface
{
    /**
     * @param AccountResource $resource
     * @param AccountFactory $accountFactory
     * @param CollectionFactory $collectionFactory
     * @param AccountSearchResultsInterfaceFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param Config $config
     */
    public function __construct(
        private readonly AccountResource $resource,
        private readonly AccountFactory $accountFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly AccountSearchResultsInterfaceFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly Config $config,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(AccountInterface $account): AccountInterface
    {
        try {
            $this->resource->save($account);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save reward points account: %1', $e->getMessage()),
                $e,
            );
        }

        return $account;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $accountId): AccountInterface
    {
        $account = $this->accountFactory->create();
        $this->resource->load($account, $accountId);

        if (!$account->getAccountId()) {
            throw new NoSuchEntityException(
                __('Reward points account with ID "%1" does not exist.', $accountId),
            );
        }

        return $account;
    }

    /**
     * {@inheritdoc}
     */
    public function getByCustomer(int $customerId, int $websiteId): AccountInterface
    {
        $account = $this->accountFactory->create();
        $this->resource->loadByCustomerWebsite($account, $customerId, $websiteId);

        if (!$account->getAccountId()) {
            throw new NoSuchEntityException(
                __(
                    'Reward points account for customer "%1" on website "%2" does not exist.',
                    $customerId,
                    $websiteId,
                ),
            );
        }

        return $account;
    }

    /**
     * {@inheritdoc}
     */
    public function getOrCreate(int $customerId, int $websiteId): AccountInterface
    {
        try {
            return $this->getByCustomer($customerId, $websiteId);
        } catch (NoSuchEntityException) {
            $subscribeByDefault = $this->config->isSubscribeByDefault();
            $account = $this->accountFactory->create();
            $account->setCustomerId($customerId);
            $account->setWebsiteId($websiteId);
            $account->setPointsBalance(0);
            $account->setTotalEarned(0);
            $account->setTotalSpent(0);
            $account->setIsEnabled(true);
            $account->setIsSubscribedBalance($subscribeByDefault);
            $account->setIsSubscribedExpiration($subscribeByDefault);

            return $this->save($account);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function delete(AccountInterface $account): bool
    {
        try {
            $this->resource->delete($account);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete reward points account: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById(int $accountId): bool
    {
        return $this->delete($this->getById($accountId));
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria): AccountSearchResultsInterface
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

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Api\SearchCriteria\CollectionProcessorInterface;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\SpendingRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule as SpendingRuleResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\RuleJunction;

/**
 * Spending Rule Repository Implementation
 */
class SpendingRuleRepository implements SpendingRuleRepositoryInterface
{
    private const RULE_TYPE = 'spending';

    /**
     * @param SpendingRuleResource $resource
     * @param SpendingRuleFactory $ruleFactory
     * @param CollectionFactory $collectionFactory
     * @param SearchResultsFactory $searchResultsFactory
     * @param CollectionProcessorInterface $collectionProcessor
     * @param RuleJunction $ruleJunction
     */
    public function __construct(
        private readonly SpendingRuleResource $resource,
        private readonly SpendingRuleFactory $ruleFactory,
        private readonly CollectionFactory $collectionFactory,
        private readonly SearchResultsFactory $searchResultsFactory,
        private readonly CollectionProcessorInterface $collectionProcessor,
        private readonly RuleJunction $ruleJunction,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(SpendingRule $rule): SpendingRule
    {
        try {
            $this->resource->save($rule);

            $ruleId = (int) $rule->getRuleId();

            $websiteIds = $rule->getData('website_ids');

            if (is_array($websiteIds)) {
                $this->ruleJunction->saveWebsites(
                    $ruleId,
                    self::RULE_TYPE,
                    array_map('intval', $websiteIds),
                );
            }

            $groupIds = $rule->getData('customer_group_ids');

            if (is_array($groupIds)) {
                $this->ruleJunction->saveCustomerGroups(
                    $ruleId,
                    self::RULE_TYPE,
                    array_map('intval', $groupIds),
                );
            }
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save spending rule: %1', $e->getMessage()),
                $e,
            );
        }

        return $rule;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $ruleId): SpendingRule
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $ruleId);

        if (!$rule->getRuleId()) {
            throw new NoSuchEntityException(
                __('Spending rule with ID "%1" does not exist.', $ruleId),
            );
        }

        $rule->setData('website_ids', $this->ruleJunction->getWebsiteIds($ruleId, self::RULE_TYPE));
        $rule->setData('customer_group_ids', $this->ruleJunction->getCustomerGroupIds($ruleId, self::RULE_TYPE));

        return $rule;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(SpendingRule $rule): bool
    {
        try {
            $ruleId = (int) $rule->getRuleId();
            $this->ruleJunction->deleteByRule($ruleId, self::RULE_TYPE);
            $this->resource->delete($rule);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete spending rule: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteById(int $ruleId): bool
    {
        return $this->delete($this->getById($ruleId));
    }

    /**
     * {@inheritdoc}
     */
    public function getList(SearchCriteriaInterface $searchCriteria): \Magento\Framework\Api\SearchResultsInterface
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

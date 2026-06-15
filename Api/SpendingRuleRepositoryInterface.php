<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Model\Rule\SpendingRule;

/**
 * Spending Rule Repository Interface
 *
 * @api
 */
interface SpendingRuleRepositoryInterface
{
    /**
     * Save spending rule
     *
     * @param SpendingRule $rule
     * @return SpendingRule
     * @throws CouldNotSaveException
     */
    public function save(SpendingRule $rule): SpendingRule;

    /**
     * Get spending rule by ID
     *
     * @param int $ruleId
     * @return SpendingRule
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): SpendingRule;

    /**
     * Delete spending rule
     *
     * @param SpendingRule $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(SpendingRule $rule): bool;

    /**
     * Delete spending rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws CouldNotDeleteException
     * @throws NoSuchEntityException
     */
    public function deleteById(int $ruleId): bool;

    /**
     * Get list of spending rules
     *
     * @param SearchCriteriaInterface $searchCriteria
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getList(SearchCriteriaInterface $searchCriteria): \Magento\Framework\Api\SearchResultsInterface;
}

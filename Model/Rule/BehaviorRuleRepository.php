<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\BehaviorRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\BehaviorRule as BehaviorRuleResource;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRuleFactory;

/**
 * Behavior Rule Repository
 */
class BehaviorRuleRepository implements BehaviorRuleRepositoryInterface
{
    /**
     * @param BehaviorRuleResource $resource
     * @param BehaviorRuleFactory $ruleFactory
     */
    public function __construct(
        private readonly BehaviorRuleResource $resource,
        private readonly BehaviorRuleFactory $ruleFactory,
    ) {
    }

    /**
     * Save behavior rule
     *
     * @param BehaviorRule $rule
     * @return BehaviorRule
     * @throws CouldNotSaveException
     */
    public function save(BehaviorRule $rule): BehaviorRule
    {
        try {
            $this->resource->save($rule);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save Behavior Earning Rule: %1', $e->getMessage()),
                $e,
            );
        }

        return $rule;
    }

    /**
     * Get behavior rule by ID
     *
     * @param int $ruleId
     * @return BehaviorRule
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): BehaviorRule
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $ruleId);

        if (!$rule->getId()) {
            throw new NoSuchEntityException(
                __('Behavior Earning Rule with ID "%1" does not exist.', $ruleId),
            );
        }

        return $rule;
    }

    /**
     * Delete behavior rule
     *
     * @param BehaviorRule $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(BehaviorRule $rule): bool
    {
        try {
            $this->resource->delete($rule);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete Behavior Earning Rule: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * Delete behavior rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $ruleId): bool
    {
        return $this->delete($this->getById($ruleId));
    }
}

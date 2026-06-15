<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Meetanshi\RewardPoints\Model\Rule\BehaviorRule;

/**
 * Behavior Earning Rule Repository Interface
 *
 * @api
 */
interface BehaviorRuleRepositoryInterface
{
    /**
     * Save behavior rule
     *
     * @param BehaviorRule $rule
     * @return BehaviorRule
     * @throws \Magento\Framework\Exception\CouldNotSaveException
     */
    public function save(BehaviorRule $rule): BehaviorRule;

    /**
     * Get behavior rule by ID
     *
     * @param int $ruleId
     * @return BehaviorRule
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getById(int $ruleId): BehaviorRule;

    /**
     * Delete behavior rule
     *
     * @param BehaviorRule $rule
     * @return bool
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function delete(BehaviorRule $rule): bool;

    /**
     * Delete behavior rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     * @throws \Magento\Framework\Exception\CouldNotDeleteException
     */
    public function deleteById(int $ruleId): bool;
}

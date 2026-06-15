<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\ReferralRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\ReferralRule as ReferralRuleResource;
use Meetanshi\RewardPoints\Model\Rule\ReferralRuleFactory;

/**
 * Referral Rule Repository
 */
class ReferralRuleRepository implements ReferralRuleRepositoryInterface
{
    /**
     * @param ReferralRuleResource $resource
     * @param ReferralRuleFactory $ruleFactory
     */
    public function __construct(
        private readonly ReferralRuleResource $resource,
        private readonly ReferralRuleFactory $ruleFactory,
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function save(ReferralRule $rule): ReferralRule
    {
        try {
            $this->resource->save($rule);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save referral rule: %1', $e->getMessage()),
                $e,
            );
        }

        return $rule;
    }

    /**
     * {@inheritdoc}
     */
    public function getById(int $ruleId): ReferralRule
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $ruleId);

        if (!$rule->getRuleId()) {
            throw new NoSuchEntityException(
                __('Referral rule with ID "%1" does not exist.', $ruleId),
            );
        }

        return $rule;
    }

    /**
     * {@inheritdoc}
     */
    public function delete(ReferralRule $rule): bool
    {
        try {
            $this->resource->delete($rule);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete referral rule: %1', $e->getMessage()),
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
}

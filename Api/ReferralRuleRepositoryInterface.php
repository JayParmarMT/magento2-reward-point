<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Model\Rule\ReferralRule;

/**
 * Referral Rule Repository Interface
 *
 * @api
 */
interface ReferralRuleRepositoryInterface
{
    /**
     * Save referral rule
     *
     * @param ReferralRule $rule
     * @return ReferralRule
     * @throws CouldNotSaveException
     */
    public function save(ReferralRule $rule): ReferralRule;

    /**
     * Get referral rule by ID
     *
     * @param int $ruleId
     * @return ReferralRule
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): ReferralRule;

    /**
     * Delete referral rule
     *
     * @param ReferralRule $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(ReferralRule $rule): bool;

    /**
     * Delete referral rule by ID
     *
     * @param int $ruleId
     * @return bool
     * @throws NoSuchEntityException
     * @throws CouldNotDeleteException
     */
    public function deleteById(int $ruleId): bool;
}

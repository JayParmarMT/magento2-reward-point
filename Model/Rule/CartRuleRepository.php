<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Meetanshi\RewardPoints\Api\CartRuleRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule as CartRuleResource;
use Meetanshi\RewardPoints\Model\Rule\CartRuleFactory;

/**
 * Cart Rule Repository
 */
class CartRuleRepository implements CartRuleRepositoryInterface
{
    /**
     * @param CartRuleResource $resource
     * @param CartRuleFactory $ruleFactory
     */
    public function __construct(
        private readonly CartRuleResource $resource,
        private readonly CartRuleFactory $ruleFactory,
    ) {
    }

    /**
     * Save cart rule
     *
     * @param CartRule $rule
     * @return CartRule
     * @throws CouldNotSaveException
     */
    public function save(CartRule $rule): CartRule
    {
        try {
            $this->resource->save($rule);
        } catch (\Exception $e) {
            throw new CouldNotSaveException(
                __('Could not save Cart Earning Rule: %1', $e->getMessage()),
                $e,
            );
        }

        return $rule;
    }

    /**
     * Get cart rule by ID
     *
     * @param int $ruleId
     * @return CartRule
     * @throws NoSuchEntityException
     */
    public function getById(int $ruleId): CartRule
    {
        $rule = $this->ruleFactory->create();
        $this->resource->load($rule, $ruleId);

        if (!$rule->getId()) {
            throw new NoSuchEntityException(
                __('Cart Earning Rule with ID "%1" does not exist.', $ruleId),
            );
        }

        return $rule;
    }

    /**
     * Delete cart rule
     *
     * @param CartRule $rule
     * @return bool
     * @throws CouldNotDeleteException
     */
    public function delete(CartRule $rule): bool
    {
        try {
            $this->resource->delete($rule);
        } catch (\Exception $e) {
            throw new CouldNotDeleteException(
                __('Could not delete Cart Earning Rule: %1', $e->getMessage()),
                $e,
            );
        }

        return true;
    }

    /**
     * Delete cart rule by ID
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

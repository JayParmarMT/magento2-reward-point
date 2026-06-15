<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\SpendingRule as SpendingRuleResource;

/**
 * Reward Points Cart Spending Rule Model
 */
class SpendingRule extends AbstractModel
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const FROM_DATE = 'from_date';
    public const TO_DATE = 'to_date';
    public const PRIORITY = 'priority';
    public const SPENDING_STYLE = 'spending_style';
    public const SPENDING_ACTION = 'spending_action';
    public const DISCOUNT_ACTION = 'discount_action';
    public const POINTS_STEP = 'points_step';
    public const DISCOUNT_AMOUNT = 'discount_amount';
    public const MAX_POINTS_PER_ORDER = 'max_points_per_order';
    public const STOP_RULES_PROCESSING = 'stop_rules_processing';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const ACTIONS_SERIALIZED = 'actions_serialized';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(SpendingRuleResource::class);
    }

    /**
     * Get rule ID
     *
     * @return int|null
     */
    public function getRuleId(): ?int
    {
        return $this->getData(self::RULE_ID) ? (int) $this->getData(self::RULE_ID) : null;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName(): string
    {
        return (string) $this->getData(self::NAME);
    }

    /**
     * Set name
     *
     * @param string $name
     * @return static
     */
    public function setName(string $name): static
    {
        return $this->setData(self::NAME, $name);
    }

    /**
     * Get description
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getData(self::DESCRIPTION);
    }

    /**
     * Set description
     *
     * @param string|null $description
     * @return static
     */
    public function setDescription(?string $description): static
    {
        return $this->setData(self::DESCRIPTION, $description);
    }

    /**
     * Is active
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return (bool) $this->getData(self::IS_ACTIVE);
    }

    /**
     * Set is active
     *
     * @param bool $isActive
     * @return static
     */
    public function setIsActive(bool $isActive): static
    {
        return $this->setData(self::IS_ACTIVE, (int) $isActive);
    }

    /**
     * Get from date
     *
     * @return string|null
     */
    public function getFromDate(): ?string
    {
        return $this->getData(self::FROM_DATE);
    }

    /**
     * Set from date
     *
     * @param string|null $fromDate
     * @return static
     */
    public function setFromDate(?string $fromDate): static
    {
        return $this->setData(self::FROM_DATE, $fromDate);
    }

    /**
     * Get to date
     *
     * @return string|null
     */
    public function getToDate(): ?string
    {
        return $this->getData(self::TO_DATE);
    }

    /**
     * Set to date
     *
     * @param string|null $toDate
     * @return static
     */
    public function setToDate(?string $toDate): static
    {
        return $this->setData(self::TO_DATE, $toDate);
    }

    /**
     * Get priority
     *
     * @return int
     */
    public function getPriority(): int
    {
        return (int) $this->getData(self::PRIORITY);
    }

    /**
     * Set priority
     *
     * @param int $priority
     * @return static
     */
    public function setPriority(int $priority): static
    {
        return $this->setData(self::PRIORITY, $priority);
    }

    /**
     * Get spending style
     *
     * @return string
     */
    public function getSpendingStyle(): string
    {
        return (string) $this->getData(self::SPENDING_STYLE);
    }

    /**
     * Set spending style
     *
     * @param string $spendingStyle
     * @return static
     */
    public function setSpendingStyle(string $spendingStyle): static
    {
        return $this->setData(self::SPENDING_STYLE, $spendingStyle);
    }

    /**
     * Get spending action
     *
     * @return string
     */
    public function getSpendingAction(): string
    {
        return (string) $this->getData(self::SPENDING_ACTION);
    }

    /**
     * Set spending action
     *
     * @param string $spendingAction
     * @return static
     */
    public function setSpendingAction(string $spendingAction): static
    {
        return $this->setData(self::SPENDING_ACTION, $spendingAction);
    }

    /**
     * Get discount action
     *
     * @return string
     */
    public function getDiscountAction(): string
    {
        return (string) $this->getData(self::DISCOUNT_ACTION);
    }

    /**
     * Set discount action
     *
     * @param string $discountAction
     * @return static
     */
    public function setDiscountAction(string $discountAction): static
    {
        return $this->setData(self::DISCOUNT_ACTION, $discountAction);
    }

    /**
     * Get points step
     *
     * @return int
     */
    public function getPointsStep(): int
    {
        return (int) $this->getData(self::POINTS_STEP);
    }

    /**
     * Set points step
     *
     * @param int $pointsStep
     * @return static
     */
    public function setPointsStep(int $pointsStep): static
    {
        return $this->setData(self::POINTS_STEP, $pointsStep);
    }

    /**
     * Get discount amount
     *
     * @return float
     */
    public function getDiscountAmount(): float
    {
        return (float) $this->getData(self::DISCOUNT_AMOUNT);
    }

    /**
     * Set discount amount
     *
     * @param float $discountAmount
     * @return static
     */
    public function setDiscountAmount(float $discountAmount): static
    {
        return $this->setData(self::DISCOUNT_AMOUNT, $discountAmount);
    }

    /**
     * Get max points per order
     *
     * @return int
     */
    public function getMaxPointsPerOrder(): int
    {
        return (int) $this->getData(self::MAX_POINTS_PER_ORDER);
    }

    /**
     * Set max points per order
     *
     * @param int $maxPointsPerOrder
     * @return static
     */
    public function setMaxPointsPerOrder(int $maxPointsPerOrder): static
    {
        return $this->setData(self::MAX_POINTS_PER_ORDER, $maxPointsPerOrder);
    }

    /**
     * Is stop rules processing
     *
     * @return bool
     */
    public function isStopRulesProcessing(): bool
    {
        return (bool) $this->getData(self::STOP_RULES_PROCESSING);
    }

    /**
     * Set stop rules processing
     *
     * @param bool $stop
     * @return static
     */
    public function setStopRulesProcessing(bool $stop): static
    {
        return $this->setData(self::STOP_RULES_PROCESSING, (int) $stop);
    }

    /**
     * Get conditions serialized
     *
     * @return string|null
     */
    public function getConditionsSerialized(): ?string
    {
        return $this->getData(self::CONDITIONS_SERIALIZED);
    }

    /**
     * Set conditions serialized
     *
     * @param string|null $conditions
     * @return static
     */
    public function setConditionsSerialized(?string $conditions): static
    {
        return $this->setData(self::CONDITIONS_SERIALIZED, $conditions);
    }

    /**
     * Get actions serialized
     *
     * @return string|null
     */
    public function getActionsSerialized(): ?string
    {
        return $this->getData(self::ACTIONS_SERIALIZED);
    }

    /**
     * Set actions serialized
     *
     * @param string|null $actions
     * @return static
     */
    public function setActionsSerialized(?string $actions): static
    {
        return $this->setData(self::ACTIONS_SERIALIZED, $actions);
    }

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}

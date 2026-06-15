<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\CartRule as CartRuleResource;

/**
 * Reward Points Cart Earning Rule Model
 */
class CartRule extends AbstractModel
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const FROM_DATE = 'from_date';
    public const TO_DATE = 'to_date';
    public const PRIORITY = 'priority';
    public const ACTION_TYPE = 'action_type';
    public const POINTS = 'points';
    public const MONEY_STEP = 'money_step';
    public const MAX_POINTS = 'max_points';
    public const STOP_RULES_PROCESSING = 'stop_rules_processing';
    public const IS_SHOWN_ON_PRODUCT_PAGE = 'is_shown_on_product_page';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const ACTIONS_SERIALIZED = 'actions_serialized';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(CartRuleResource::class);
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
     * Get action type
     *
     * @return string
     */
    public function getActionType(): string
    {
        return (string) $this->getData(self::ACTION_TYPE);
    }

    /**
     * Set action type
     *
     * @param string $actionType
     * @return static
     */
    public function setActionType(string $actionType): static
    {
        return $this->setData(self::ACTION_TYPE, $actionType);
    }

    /**
     * Get points
     *
     * @return int
     */
    public function getPoints(): int
    {
        return (int) $this->getData(self::POINTS);
    }

    /**
     * Set points
     *
     * @param int $points
     * @return static
     */
    public function setPoints(int $points): static
    {
        return $this->setData(self::POINTS, $points);
    }

    /**
     * Get money step
     *
     * @return float|null
     */
    public function getMoneyStep(): ?float
    {
        return $this->getData(self::MONEY_STEP) !== null ? (float) $this->getData(self::MONEY_STEP) : null;
    }

    /**
     * Set money step
     *
     * @param float|null $moneyStep
     * @return static
     */
    public function setMoneyStep(?float $moneyStep): static
    {
        return $this->setData(self::MONEY_STEP, $moneyStep);
    }

    /**
     * Get max points
     *
     * @return int
     */
    public function getMaxPoints(): int
    {
        return (int) $this->getData(self::MAX_POINTS);
    }

    /**
     * Set max points
     *
     * @param int $maxPoints
     * @return static
     */
    public function setMaxPoints(int $maxPoints): static
    {
        return $this->setData(self::MAX_POINTS, $maxPoints);
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
     * Is shown on product page
     *
     * @return bool
     */
    public function isShownOnProductPage(): bool
    {
        return (bool) $this->getData(self::IS_SHOWN_ON_PRODUCT_PAGE);
    }

    /**
     * Set is shown on product page
     *
     * @param bool $show
     * @return static
     */
    public function setIsShownOnProductPage(bool $show): static
    {
        return $this->setData(self::IS_SHOWN_ON_PRODUCT_PAGE, (int) $show);
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

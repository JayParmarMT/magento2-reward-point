<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\BehaviorRule as BehaviorRuleResource;

/**
 * Reward Points Behavior Earning Rule Model
 */
class BehaviorRule extends AbstractModel
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const RULE_CODE = 'event_code';
    public const POINTS = 'points';
    public const MAX_POINTS = 'max_points';
    public const MAX_POINTS_PER_CUSTOMER = 'max_points_per_customer';
    public const CUSTOMER_GROUP_IDS = 'customer_group_ids';
    public const WEBSITE_IDS = 'website_ids';
    public const FROM_DATE = 'from_date';
    public const TO_DATE = 'to_date';
    public const PRIORITY = 'priority';
    public const STOP_RULES_PROCESSING = 'stop_rules_processing';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(BehaviorRuleResource::class);
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
     * Get rule code
     *
     * @return string
     */
    public function getRuleCode(): string
    {
        return (string) $this->getData(self::RULE_CODE);
    }

    /**
     * Set rule code
     *
     * @param string $ruleCode
     * @return static
     */
    public function setRuleCode(string $ruleCode): static
    {
        return $this->setData(self::RULE_CODE, $ruleCode);
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
     * Get max points per customer
     *
     * @return int
     */
    public function getMaxPointsPerCustomer(): int
    {
        return (int) $this->getData(self::MAX_POINTS_PER_CUSTOMER);
    }

    /**
     * Set max points per customer
     *
     * @param int $maxPointsPerCustomer
     * @return static
     */
    public function setMaxPointsPerCustomer(int $maxPointsPerCustomer): static
    {
        return $this->setData(self::MAX_POINTS_PER_CUSTOMER, $maxPointsPerCustomer);
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

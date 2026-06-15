<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Model\ResourceModel\BehaviorLog as BehaviorLogResource;

/**
 * Reward Points Behavior Log Model
 *
 * Tracks per-customer, per-rule behavior point accumulation for cap enforcement.
 * Maps to meetanshi_rewardpoints_behavior_log table.
 */
class BehaviorLog extends AbstractModel
{
    public const LOG_ID = 'log_id';
    public const CUSTOMER_ID = 'customer_id';
    public const RULE_ID = 'rule_id';
    public const EVENT_CODE = 'event_code';
    /** @deprecated Use EVENT_CODE — kept for backward compatibility */
    public const RULE_CODE = 'event_code';
    public const POINTS_EARNED_TODAY = 'points_earned_today';
    public const POINTS_EARNED_THIS_MONTH = 'points_earned_this_month';
    public const POINTS_EARNED_THIS_YEAR = 'points_earned_this_year';
    public const POINTS_EARNED_LIFETIME = 'points_earned_lifetime';
    public const LAST_EARNED_DATE = 'last_earned_date';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(BehaviorLogResource::class);
    }

    /**
     * Get log ID
     *
     * @return int|null
     */
    public function getLogId(): ?int
    {
        return $this->getData(self::LOG_ID) ? (int) $this->getData(self::LOG_ID) : null;
    }

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return static
     */
    public function setCustomerId(int $customerId): static
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
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
     * Set rule ID
     *
     * @param int|null $ruleId
     * @return static
     */
    public function setRuleId(?int $ruleId): static
    {
        return $this->setData(self::RULE_ID, $ruleId);
    }

    /**
     * Get event code (e.g. 'signup', 'review', 'refer_signup')
     *
     * @return string
     */
    public function getEventCode(): string
    {
        return (string) $this->getData(self::EVENT_CODE);
    }

    /**
     * Set event code
     *
     * @param string $eventCode
     * @return static
     */
    public function setEventCode(string $eventCode): static
    {
        return $this->setData(self::EVENT_CODE, $eventCode);
    }

    /**
     * Get rule code (alias for getEventCode — backward compat)
     *
     * @return string
     */
    public function getRuleCode(): string
    {
        return $this->getEventCode();
    }

    /**
     * Set rule code (alias for setEventCode — backward compat)
     *
     * @param string $ruleCode
     * @return static
     */
    public function setRuleCode(string $ruleCode): static
    {
        return $this->setEventCode($ruleCode);
    }

    /**
     * Get points earned today
     *
     * @return int
     */
    public function getPointsEarnedToday(): int
    {
        return (int) $this->getData(self::POINTS_EARNED_TODAY);
    }

    /**
     * Set points earned today
     *
     * @param int $points
     * @return static
     */
    public function setPointsEarnedToday(int $points): static
    {
        return $this->setData(self::POINTS_EARNED_TODAY, $points);
    }

    /**
     * Get points earned this month
     *
     * @return int
     */
    public function getPointsEarnedThisMonth(): int
    {
        return (int) $this->getData(self::POINTS_EARNED_THIS_MONTH);
    }

    /**
     * Set points earned this month
     *
     * @param int $points
     * @return static
     */
    public function setPointsEarnedThisMonth(int $points): static
    {
        return $this->setData(self::POINTS_EARNED_THIS_MONTH, $points);
    }

    /**
     * Get points earned this year
     *
     * @return int
     */
    public function getPointsEarnedThisYear(): int
    {
        return (int) $this->getData(self::POINTS_EARNED_THIS_YEAR);
    }

    /**
     * Set points earned this year
     *
     * @param int $points
     * @return static
     */
    public function setPointsEarnedThisYear(int $points): static
    {
        return $this->setData(self::POINTS_EARNED_THIS_YEAR, $points);
    }

    /**
     * Get lifetime points earned
     *
     * @return int
     */
    public function getPointsEarnedLifetime(): int
    {
        return (int) $this->getData(self::POINTS_EARNED_LIFETIME);
    }

    /**
     * Set lifetime points earned
     *
     * @param int $points
     * @return static
     */
    public function setPointsEarnedLifetime(int $points): static
    {
        return $this->setData(self::POINTS_EARNED_LIFETIME, $points);
    }

    /**
     * Get last earned date (Y-m-d string)
     *
     * @return string|null
     */
    public function getLastEarnedDate(): ?string
    {
        return $this->getData(self::LAST_EARNED_DATE)
            ? (string) $this->getData(self::LAST_EARNED_DATE)
            : null;
    }

    /**
     * Set last earned date
     *
     * @param string|null $date
     * @return static
     */
    public function setLastEarnedDate(?string $date): static
    {
        return $this->setData(self::LAST_EARNED_DATE, $date);
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

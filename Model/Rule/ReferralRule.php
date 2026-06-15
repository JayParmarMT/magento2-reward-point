<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\ReferralRule as ReferralRuleResource;

/**
 * Reward Points Referral Rule Model
 */
class ReferralRule extends AbstractModel
{
    public const RULE_ID = 'rule_id';
    public const NAME = 'name';
    public const DESCRIPTION = 'description';
    public const IS_ACTIVE = 'is_active';
    public const FROM_DATE = 'from_date';
    public const TO_DATE = 'to_date';
    public const REFERRER_POINTS = 'referrer_points';
    public const REFEREE_POINTS = 'referee_points';
    public const REFEREE_DISCOUNT = 'referee_discount';
    public const DISCOUNT_TYPE = 'discount_type';
    public const MAX_INVITATIONS = 'max_invitations';
    public const CUSTOMER_GROUP_IDS = 'customer_group_ids';
    public const WEBSITE_IDS = 'website_ids';
    public const CONDITIONS_SERIALIZED = 'conditions_serialized';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ReferralRuleResource::class);
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
     * Get referrer points
     *
     * @return int
     */
    public function getReferrerPoints(): int
    {
        return (int) $this->getData(self::REFERRER_POINTS);
    }

    /**
     * Set referrer points
     *
     * @param int $points
     * @return static
     */
    public function setReferrerPoints(int $points): static
    {
        return $this->setData(self::REFERRER_POINTS, $points);
    }

    /**
     * Get referee points
     *
     * @return int
     */
    public function getRefereePoints(): int
    {
        return (int) $this->getData(self::REFEREE_POINTS);
    }

    /**
     * Set referee points
     *
     * @param int $points
     * @return static
     */
    public function setRefereePoints(int $points): static
    {
        return $this->setData(self::REFEREE_POINTS, $points);
    }

    /**
     * Get referee discount
     *
     * @return float
     */
    public function getRefereeDiscount(): float
    {
        return (float) $this->getData(self::REFEREE_DISCOUNT);
    }

    /**
     * Set referee discount
     *
     * @param float $discount
     * @return static
     */
    public function setRefereeDiscount(float $discount): static
    {
        return $this->setData(self::REFEREE_DISCOUNT, $discount);
    }

    /**
     * Get discount type
     *
     * @return string
     */
    public function getDiscountType(): string
    {
        return (string) $this->getData(self::DISCOUNT_TYPE);
    }

    /**
     * Set discount type
     *
     * @param string $discountType
     * @return static
     */
    public function setDiscountType(string $discountType): static
    {
        return $this->setData(self::DISCOUNT_TYPE, $discountType);
    }

    /**
     * Get max invitations
     *
     * @return int
     */
    public function getMaxInvitations(): int
    {
        return (int) $this->getData(self::MAX_INVITATIONS);
    }

    /**
     * Set max invitations
     *
     * @param int $maxInvitations
     * @return static
     */
    public function setMaxInvitations(int $maxInvitations): static
    {
        return $this->setData(self::MAX_INVITATIONS, $maxInvitations);
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

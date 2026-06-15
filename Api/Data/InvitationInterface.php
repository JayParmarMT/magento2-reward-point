<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

/**
 * Reward Points Invitation Data Interface
 *
 * @api
 */
interface InvitationInterface
{
    public const INVITATION_ID = 'invitation_id';
    public const REFERRER_CUSTOMER_ID = 'referrer_customer_id';
    public const REFEREE_CUSTOMER_ID = 'referee_customer_id';
    public const WEBSITE_ID = 'website_id';
    public const REFEREE_EMAIL = 'referee_email';
    public const REFERRAL_CODE = 'referral_code';
    public const STATUS = 'status';
    public const REFERRER_POINTS_EARNED = 'referrer_points_earned';
    public const REFEREE_DISCOUNT_EARNED = 'referee_discount_earned';
    public const REFEREE_POINTS_EARNED = 'referee_points_earned';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SIGNED_UP = 'signed_up';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    /**
     * Get invitation ID
     *
     * @return int|null
     */
    public function getInvitationId(): ?int;

    /**
     * Set invitation ID
     *
     * @param int $invitationId
     * @return static
     */
    public function setInvitationId(int $invitationId): static;

    /**
     * Get referrer customer ID
     *
     * @return int
     */
    public function getReferrerCustomerId(): int;

    /**
     * Set referrer customer ID
     *
     * @param int $referrerCustomerId
     * @return static
     */
    public function setReferrerCustomerId(int $referrerCustomerId): static;

    /**
     * Get referee customer ID
     *
     * @return int|null
     */
    public function getRefereeCustomerId(): ?int;

    /**
     * Set referee customer ID
     *
     * @param int|null $refereeCustomerId
     * @return static
     */
    public function setRefereeCustomerId(?int $refereeCustomerId): static;

    /**
     * Get website ID
     *
     * @return int
     */
    public function getWebsiteId(): int;

    /**
     * Set website ID
     *
     * @param int $websiteId
     * @return static
     */
    public function setWebsiteId(int $websiteId): static;

    /**
     * Get referee email
     *
     * @return string
     */
    public function getRefereeEmail(): string;

    /**
     * Set referee email
     *
     * @param string $refereeEmail
     * @return static
     */
    public function setRefereeEmail(string $refereeEmail): static;

    /**
     * Get referral code
     *
     * @return string
     */
    public function getReferralCode(): string;

    /**
     * Set referral code
     *
     * @param string $referralCode
     * @return static
     */
    public function setReferralCode(string $referralCode): static;

    /**
     * Get status
     *
     * @return string
     */
    public function getStatus(): string;

    /**
     * Set status
     *
     * @param string $status
     * @return static
     */
    public function setStatus(string $status): static;

    /**
     * Get referrer points earned
     *
     * @return int
     */
    public function getReferrerPointsEarned(): int;

    /**
     * Set referrer points earned
     *
     * @param int $points
     * @return static
     */
    public function setReferrerPointsEarned(int $points): static;

    /**
     * Get referee discount earned
     *
     * @return float
     */
    public function getRefereeDiscountEarned(): float;

    /**
     * Set referee discount earned
     *
     * @param float $discount
     * @return static
     */
    public function setRefereeDiscountEarned(float $discount): static;

    /**
     * Get referee points earned
     *
     * @return int
     */
    public function getRefereePointsEarned(): int;

    /**
     * Set referee points earned
     *
     * @param int $points
     * @return static
     */
    public function setRefereePointsEarned(int $points): static;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;

    /**
     * Get updated at
     *
     * @return string|null
     */
    public function getUpdatedAt(): ?string;
}

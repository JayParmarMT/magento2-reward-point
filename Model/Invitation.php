<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\InvitationInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation as InvitationResource;

/**
 * Reward Points Invitation Model
 */
class Invitation extends AbstractModel implements InvitationInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(InvitationResource::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getInvitationId(): ?int
    {
        return $this->getData(self::INVITATION_ID) ? (int) $this->getData(self::INVITATION_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setInvitationId(int $invitationId): static
    {
        return $this->setData(self::INVITATION_ID, $invitationId);
    }

    /**
     * {@inheritdoc}
     */
    public function getReferrerCustomerId(): int
    {
        return (int) $this->getData(self::REFERRER_CUSTOMER_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setReferrerCustomerId(int $referrerCustomerId): static
    {
        return $this->setData(self::REFERRER_CUSTOMER_ID, $referrerCustomerId);
    }

    /**
     * {@inheritdoc}
     */
    public function getRefereeCustomerId(): ?int
    {
        return $this->getData(self::REFEREE_CUSTOMER_ID) ? (int) $this->getData(self::REFEREE_CUSTOMER_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setRefereeCustomerId(?int $refereeCustomerId): static
    {
        return $this->setData(self::REFEREE_CUSTOMER_ID, $refereeCustomerId);
    }

    /**
     * {@inheritdoc}
     */
    public function getWebsiteId(): int
    {
        return (int) $this->getData(self::WEBSITE_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setWebsiteId(int $websiteId): static
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * {@inheritdoc}
     */
    public function getRefereeEmail(): string
    {
        return (string) $this->getData(self::REFEREE_EMAIL);
    }

    /**
     * {@inheritdoc}
     */
    public function setRefereeEmail(string $refereeEmail): static
    {
        return $this->setData(self::REFEREE_EMAIL, $refereeEmail);
    }

    /**
     * {@inheritdoc}
     */
    public function getReferralCode(): string
    {
        return (string) $this->getData(self::REFERRAL_CODE);
    }

    /**
     * {@inheritdoc}
     */
    public function setReferralCode(string $referralCode): static
    {
        return $this->setData(self::REFERRAL_CODE, $referralCode);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatus(): string
    {
        return (string) $this->getData(self::STATUS);
    }

    /**
     * {@inheritdoc}
     */
    public function setStatus(string $status): static
    {
        return $this->setData(self::STATUS, $status);
    }

    /**
     * {@inheritdoc}
     */
    public function getReferrerPointsEarned(): int
    {
        return (int) $this->getData(self::REFERRER_POINTS_EARNED);
    }

    /**
     * {@inheritdoc}
     */
    public function setReferrerPointsEarned(int $points): static
    {
        return $this->setData(self::REFERRER_POINTS_EARNED, $points);
    }

    /**
     * {@inheritdoc}
     */
    public function getRefereeDiscountEarned(): float
    {
        return (float) $this->getData(self::REFEREE_DISCOUNT_EARNED);
    }

    /**
     * {@inheritdoc}
     */
    public function setRefereeDiscountEarned(float $discount): static
    {
        return $this->setData(self::REFEREE_DISCOUNT_EARNED, $discount);
    }

    /**
     * {@inheritdoc}
     */
    public function getRefereePointsEarned(): int
    {
        return (int) $this->getData(self::REFEREE_POINTS_EARNED);
    }

    /**
     * {@inheritdoc}
     */
    public function setRefereePointsEarned(int $points): static
    {
        return $this->setData(self::REFEREE_POINTS_EARNED, $points);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }

    /**
     * {@inheritdoc}
     */
    public function getUpdatedAt(): ?string
    {
        return $this->getData(self::UPDATED_AT);
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Model\ResourceModel\TierHistory as TierHistoryResource;

/**
 * Reward Points Tier History Model
 */
class TierHistory extends AbstractModel
{
    public const HISTORY_ID = 'history_id';
    public const ACCOUNT_ID = 'account_id';
    public const CUSTOMER_ID = 'customer_id';
    public const WEBSITE_ID = 'website_id';
    public const OLD_TIER_ID = 'old_tier_id';
    public const NEW_TIER_ID = 'new_tier_id';
    public const CHANGE_TYPE = 'change_type';
    public const CREATED_AT = 'created_at';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(TierHistoryResource::class);
    }

    /**
     * Get history ID
     *
     * @return int|null
     */
    public function getHistoryId(): ?int
    {
        return $this->getData(self::HISTORY_ID) ? (int) $this->getData(self::HISTORY_ID) : null;
    }

    /**
     * Get account ID
     *
     * @return int
     */
    public function getAccountId(): int
    {
        return (int) $this->getData(self::ACCOUNT_ID);
    }

    /**
     * Set account ID
     *
     * @param int $accountId
     * @return static
     */
    public function setAccountId(int $accountId): static
    {
        return $this->setData(self::ACCOUNT_ID, $accountId);
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
     * Get website ID
     *
     * @return int
     */
    public function getWebsiteId(): int
    {
        return (int) $this->getData(self::WEBSITE_ID);
    }

    /**
     * Set website ID
     *
     * @param int $websiteId
     * @return static
     */
    public function setWebsiteId(int $websiteId): static
    {
        return $this->setData(self::WEBSITE_ID, $websiteId);
    }

    /**
     * Get old tier ID
     *
     * @return int|null
     */
    public function getOldTierId(): ?int
    {
        return $this->getData(self::OLD_TIER_ID) ? (int) $this->getData(self::OLD_TIER_ID) : null;
    }

    /**
     * Set old tier ID
     *
     * @param int|null $oldTierId
     * @return static
     */
    public function setOldTierId(?int $oldTierId): static
    {
        return $this->setData(self::OLD_TIER_ID, $oldTierId);
    }

    /**
     * Get new tier ID
     *
     * @return int|null
     */
    public function getNewTierId(): ?int
    {
        return $this->getData(self::NEW_TIER_ID) ? (int) $this->getData(self::NEW_TIER_ID) : null;
    }

    /**
     * Set new tier ID
     *
     * @param int|null $newTierId
     * @return static
     */
    public function setNewTierId(?int $newTierId): static
    {
        return $this->setData(self::NEW_TIER_ID, $newTierId);
    }

    /**
     * Get change type
     *
     * @return string
     */
    public function getChangeType(): string
    {
        return (string) $this->getData(self::CHANGE_TYPE);
    }

    /**
     * Set change type
     *
     * @param string $changeType
     * @return static
     */
    public function setChangeType(string $changeType): static
    {
        return $this->setData(self::CHANGE_TYPE, $changeType);
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
}

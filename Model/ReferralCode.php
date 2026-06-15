<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\Model\AbstractModel;
use Meetanshi\RewardPoints\Api\Data\ReferralCodeInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode as ReferralCodeResource;

/**
 * Reward Points Referral Code Model
 */
class ReferralCode extends AbstractModel implements ReferralCodeInterface
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(ReferralCodeResource::class);
    }

    /**
     * {@inheritdoc}
     */
    public function getCodeId(): ?int
    {
        return $this->getData(self::CODE_ID) ? (int) $this->getData(self::CODE_ID) : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setCodeId(int $codeId): static
    {
        return $this->setData(self::CODE_ID, $codeId);
    }

    /**
     * {@inheritdoc}
     */
    public function getCustomerId(): int
    {
        return (int) $this->getData(self::CUSTOMER_ID);
    }

    /**
     * {@inheritdoc}
     */
    public function setCustomerId(int $customerId): static
    {
        return $this->setData(self::CUSTOMER_ID, $customerId);
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
    public function getCode(): string
    {
        return (string) $this->getData(self::CODE);
    }

    /**
     * {@inheritdoc}
     */
    public function setCode(string $code): static
    {
        return $this->setData(self::CODE, $code);
    }

    /**
     * {@inheritdoc}
     */
    public function getCreatedAt(): ?string
    {
        return $this->getData(self::CREATED_AT);
    }
}

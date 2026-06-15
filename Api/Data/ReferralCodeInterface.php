<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Api\Data;

/**
 * Reward Points Referral Code Data Interface
 *
 * @api
 */
interface ReferralCodeInterface
{
    public const CODE_ID = 'code_id';
    public const CUSTOMER_ID = 'customer_id';
    public const WEBSITE_ID = 'website_id';
    public const CODE = 'code';
    public const CREATED_AT = 'created_at';

    /**
     * Get code ID
     *
     * @return int|null
     */
    public function getCodeId(): ?int;

    /**
     * Set code ID
     *
     * @param int $codeId
     * @return static
     */
    public function setCodeId(int $codeId): static;

    /**
     * Get customer ID
     *
     * @return int
     */
    public function getCustomerId(): int;

    /**
     * Set customer ID
     *
     * @param int $customerId
     * @return static
     */
    public function setCustomerId(int $customerId): static;

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
     * Get code
     *
     * @return string
     */
    public function getCode(): string;

    /**
     * Set code
     *
     * @param string $code
     * @return static
     */
    public function setCode(string $code): static;

    /**
     * Get created at
     *
     * @return string|null
     */
    public function getCreatedAt(): ?string;
}

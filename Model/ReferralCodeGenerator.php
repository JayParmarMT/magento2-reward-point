<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode as ReferralCodeResource;
use Meetanshi\RewardPoints\Model\ResourceModel\ReferralCode\CollectionFactory as ReferralCodeCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Referral Code Generator Service
 */
class ReferralCodeGenerator
{
    private const MAX_RETRIES = 5;
    private const RANDOM_SUFFIX_LENGTH = 4;
    private const RANDOM_SUFFIX_CHARS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    private const XML_PATH_REFERRAL_CODE_PREFIX = 'meetanshi_rewardpoints/referral/code_prefix';

    /**
     * @param ReferralCodeResource $referralCodeResource
     * @param ReferralCodeCollectionFactory $collectionFactory
     * @param ReferralCodeFactory $referralCodeFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ReferralCodeResource $referralCodeResource,
        private readonly ReferralCodeCollectionFactory $collectionFactory,
        private readonly ReferralCodeFactory $referralCodeFactory,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Generate a unique referral code for the customer
     *
     * Code format: [prefix][base36(customerId)][4 random chars]
     *
     * @param int $customerId
     * @param int $websiteId
     * @return string
     * @throws LocalizedException
     */
    public function generate(int $customerId, int $websiteId): string
    {
        $prefix = $this->getPrefix($websiteId);
        $base36Id = strtoupper(base_convert((string) $customerId, 10, 36));

        for ($attempt = 1; $attempt <= self::MAX_RETRIES; $attempt++) {
            $suffix = $this->generateRandomSuffix();
            $code = $prefix . $base36Id . $suffix;

            if (!$this->codeExists($code)) {
                return $code;
            }

            $this->logger->debug(
                'RewardPoints: referral code collision, retrying',
                [
                    'customer_id' => $customerId,
                    'code' => $code,
                    'attempt' => $attempt,
                ],
            );
        }

        throw new LocalizedException(
            __(
                'Could not generate a unique referral code for customer %1 after %2 attempts.',
                $customerId,
                self::MAX_RETRIES,
            ),
        );
    }

    /**
     * Get existing code for customer or generate and save a new one
     *
     * @param int $customerId
     * @param int $websiteId
     * @return string
     * @throws LocalizedException
     */
    public function getOrCreateCode(int $customerId, int $websiteId): string
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('customer_id', $customerId);
        $collection->addFieldToFilter('website_id', $websiteId);
        $collection->setPageSize(1);

        $existing = $collection->getFirstItem();

        if ($existing->getId()) {
            return (string) $existing->getData('code');
        }

        $code = $this->generate($customerId, $websiteId);

        $referralCode = $this->referralCodeFactory->create();
        $referralCode->setCustomerId($customerId);
        $referralCode->setWebsiteId($websiteId);
        $referralCode->setCode($code);

        $this->referralCodeResource->save($referralCode);

        return $code;
    }

    /**
     * Check if a code already exists in the database
     *
     * @param string $code
     * @return bool
     */
    private function codeExists(string $code): bool
    {
        $connection = $this->referralCodeResource->getConnection();
        $select = $connection->select()
            ->from($this->referralCodeResource->getMainTable(), ['code_id'])
            ->where('code = ?', $code)
            ->limit(1);

        return (bool) $connection->fetchOne($select);
    }

    /**
     * Generate a random suffix string
     *
     * @return string
     */
    private function generateRandomSuffix(): string
    {
        $chars = self::RANDOM_SUFFIX_CHARS;
        $charsLength = strlen($chars);
        $result = '';

        for ($i = 0; $i < self::RANDOM_SUFFIX_LENGTH; $i++) {
            $result .= $chars[random_int(0, $charsLength - 1)];
        }

        return $result;
    }

    /**
     * Get configured code prefix for website
     *
     * @param int $websiteId
     * @return string
     */
    private function getPrefix(int $websiteId): string
    {
        $prefix = (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_CODE_PREFIX,
            ScopeInterface::SCOPE_WEBSITE,
            $websiteId,
        );

        return strtoupper($prefix ?: 'REF');
    }
}

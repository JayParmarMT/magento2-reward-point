<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Import;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ImportExport\Model\Import\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Psr\Log\LoggerInterface;

/**
 * Reward Points Transaction CSV importer
 *
 * Behavior: add only
 * Required columns: customer_email, website_code, points, action_code, comment, expire_after_days
 */
class Transaction extends AbstractEntity
{
    public const COL_EMAIL = 'customer_email';
    public const COL_WEBSITE_CODE = 'website_code';
    public const COL_POINTS = 'points';
    public const COL_ACTION_CODE = 'action_code';
    public const COL_COMMENT = 'comment';
    public const COL_EXPIRE_AFTER_DAYS = 'expire_after_days';

    public const ENTITY_TYPE_CODE = 'meetanshi_rewardpoints_transaction';

    /**
     * @param \Magento\Framework\Json\Helper\Data $jsonHelper
     * @param \Magento\ImportExport\Helper\Data $importExportData
     * @param \Magento\ImportExport\Model\ResourceModel\Import\Data $importData
     * @param \Magento\Framework\App\ResourceConnection $resource
     * @param \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper
     * @param \Magento\Framework\Stdlib\StringUtils $string
     * @param ProcessingErrorAggregatorInterface $errorAggregator
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param BalanceManagementInterface $balanceManagement
     * @param LoggerInterface $logger
     */
    public function __construct(
        \Magento\Framework\Json\Helper\Data $jsonHelper,
        \Magento\ImportExport\Helper\Data $importExportData,
        \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
        \Magento\Framework\App\ResourceConnection $resource,
        \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
        \Magento\Framework\Stdlib\StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly LoggerInterface $logger,
    ) {
        parent::__construct(
            $jsonHelper,
            $importExportData,
            $importData,
            $resource,
            $resourceHelper,
            $string,
            $errorAggregator,
        );

        $this->_permanentAttributes = [
            self::COL_EMAIL,
            self::COL_WEBSITE_CODE,
            self::COL_POINTS,
            self::COL_ACTION_CODE,
        ];
    }

    /**
     * Get entity type code
     *
     * @return string
     */
    public function getEntityTypeCode(): string
    {
        return self::ENTITY_TYPE_CODE;
    }

    /**
     * Validate a single import row
     *
     * @param array<string, mixed> $rowData
     * @param int $rowNum
     * @return bool
     */
    public function validateRow(array $rowData, $rowNum): bool
    {
        $email = trim((string) ($rowData[self::COL_EMAIL] ?? ''));
        $websiteCode = trim((string) ($rowData[self::COL_WEBSITE_CODE] ?? ''));
        $points = (int) ($rowData[self::COL_POINTS] ?? 0);
        $actionCode = trim((string) ($rowData[self::COL_ACTION_CODE] ?? ''));

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addRowError('Invalid or missing customer_email', $rowNum);

            return false;
        }

        if (empty($websiteCode)) {
            $this->addRowError('Missing website_code', $rowNum);

            return false;
        }

        try {
            $this->storeManager->getWebsite($websiteCode);
        } catch (NoSuchEntityException $e) {
            $this->addRowError("Website code '$websiteCode' does not exist", $rowNum);

            return false;
        }

        if ($points === 0) {
            $this->addRowError('points must be non-zero', $rowNum);

            return false;
        }

        if (empty($actionCode)) {
            $this->addRowError('action_code is required', $rowNum);

            return false;
        }

        return true;
    }

    /**
     * Import data rows (add behavior only)
     *
     * @return bool
     */
    protected function _importData(): bool
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $email = trim((string) $rowData[self::COL_EMAIL]);
                $websiteCode = trim((string) $rowData[self::COL_WEBSITE_CODE]);
                $points = (int) $rowData[self::COL_POINTS];
                $actionCode = trim((string) $rowData[self::COL_ACTION_CODE]);
                $comment = isset($rowData[self::COL_COMMENT]) ? (string) $rowData[self::COL_COMMENT] : null;
                $expireAfterDays = isset($rowData[self::COL_EXPIRE_AFTER_DAYS])
                    ? (int) $rowData[self::COL_EXPIRE_AFTER_DAYS]
                    : null;

                try {
                    $website = $this->storeManager->getWebsite($websiteCode);
                    $websiteId = (int) $website->getId();

                    $customer = $this->customerRepository->get($email, $websiteId);
                    $customerId = (int) $customer->getId();

                    if ($points > 0) {
                        $this->balanceManagement->addPoints(
                            $customerId,
                            $websiteId,
                            $points,
                            $actionCode,
                            $comment,
                            $expireAfterDays,
                        );
                    } else {
                        $this->balanceManagement->subtractPoints(
                            $customerId,
                            $websiteId,
                            abs($points),
                            $actionCode,
                            $comment,
                        );
                    }
                } catch (NoSuchEntityException $e) {
                    $this->logger->warning(
                        "RewardPoints Import Transaction: Customer not found for email $email on website $websiteCode",
                    );
                } catch (LocalizedException $e) {
                    $this->logger->error(
                        'RewardPoints Import Transaction error: ' . $e->getMessage(),
                    );
                }
            }
        }

        return true;
    }
}

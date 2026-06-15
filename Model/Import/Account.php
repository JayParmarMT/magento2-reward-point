<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Import;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\ImportExport\Model\Import\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Model\AccountFactory;
use Psr\Log\LoggerInterface;

/**
 * Reward Points Account CSV importer
 *
 * Supported behaviors: add_update, delete
 * Required columns: customer_email, website_code, points_balance, is_enabled
 */
class Account extends AbstractEntity
{
    public const BEHAVIOR_ADD_UPDATE = 'add_update';
    public const BEHAVIOR_DELETE = 'delete';

    public const COL_EMAIL = 'customer_email';
    public const COL_WEBSITE_CODE = 'website_code';
    public const COL_POINTS_BALANCE = 'points_balance';
    public const COL_IS_ENABLED = 'is_enabled';

    public const ENTITY_TYPE_CODE = 'meetanshi_rewardpoints_account';

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
     * @param AccountRepositoryInterface $accountRepository
     * @param AccountFactory $accountFactory
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
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly AccountFactory $accountFactory,
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
        ];

        $this->validColumnNames = [
            self::COL_EMAIL,
            self::COL_WEBSITE_CODE,
            self::COL_POINTS_BALANCE,
            self::COL_IS_ENABLED,
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

        if (isset($rowData[self::COL_POINTS_BALANCE])) {
            $balance = (int) $rowData[self::COL_POINTS_BALANCE];

            if ($balance < 0) {
                $this->addRowError('points_balance must be >= 0', $rowNum);

                return false;
            }
        }

        return true;
    }

    /**
     * Import data rows
     *
     * @return bool
     */
    protected function _importData(): bool
    {
        $behavior = $this->getBehavior();

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $email = trim((string) $rowData[self::COL_EMAIL]);
                $websiteCode = trim((string) $rowData[self::COL_WEBSITE_CODE]);

                try {
                    $website = $this->storeManager->getWebsite($websiteCode);
                    $websiteId = (int) $website->getId();

                    $customer = $this->customerRepository->get($email, $websiteId);
                    $customerId = (int) $customer->getId();

                    if ($behavior === self::BEHAVIOR_DELETE) {
                        $this->disableAccount($customerId, $websiteId);
                    } else {
                        $this->addUpdateAccount($customerId, $websiteId, $rowData);
                    }
                } catch (NoSuchEntityException $e) {
                    $this->logger->warning(
                        "RewardPoints Import: Customer not found for email $email on website $websiteCode",
                    );
                } catch (LocalizedException $e) {
                    $this->logger->error(
                        'RewardPoints Import Account error: ' . $e->getMessage(),
                    );
                }
            }
        }

        return true;
    }

    /**
     * Add or update account record
     *
     * @param int $customerId
     * @param int $websiteId
     * @param array<string, mixed> $rowData
     * @return void
     * @throws LocalizedException
     */
    private function addUpdateAccount(int $customerId, int $websiteId, array $rowData): void
    {
        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
        } catch (NoSuchEntityException $e) {
            $account = $this->accountFactory->create();
            $account->setCustomerId($customerId);
            $account->setWebsiteId($websiteId);
        }

        if (isset($rowData[self::COL_POINTS_BALANCE])) {
            $newBalance = (int) $rowData[self::COL_POINTS_BALANCE];
            $currentBalance = $account->getPointsBalance();
            $delta = $newBalance - $currentBalance;

            if ($delta > 0) {
                $this->balanceManagement->addPoints(
                    $customerId,
                    $websiteId,
                    $delta,
                    'import_adjust',
                    'Imported via CSV',
                );
            } elseif ($delta < 0) {
                $this->balanceManagement->subtractPoints(
                    $customerId,
                    $websiteId,
                    abs($delta),
                    'import_adjust',
                    'Imported via CSV',
                );
            }
        }

        if (isset($rowData[self::COL_IS_ENABLED])) {
            $account->setIsEnabled((bool) $rowData[self::COL_IS_ENABLED]);
            $this->accountRepository->save($account);
        }
    }

    /**
     * Disable account (soft delete behavior)
     *
     * @param int $customerId
     * @param int $websiteId
     * @return void
     */
    private function disableAccount(int $customerId, int $websiteId): void
    {
        try {
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $account->setIsEnabled(false);
            $this->accountRepository->save($account);
        } catch (NoSuchEntityException $e) {
            // Account does not exist — nothing to disable
        }
    }
}

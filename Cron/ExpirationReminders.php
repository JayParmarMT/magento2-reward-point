<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Cron;

use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Helper\Email as EmailHelper;
use Psr\Log\LoggerInterface;

/**
 * Expiration reminders cron — sends email notifications for expiring points
 */
class ExpirationReminders
{
    /**
     * @param ResourceConnection $resourceConnection
     * @param Config $config
     * @param TimezoneInterface $timezone
     * @param EmailHelper $emailHelper
     * @param AccountRepositoryInterface $accountRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly Config $config,
        private readonly TimezoneInterface $timezone,
        private readonly EmailHelper $emailHelper,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute cron job
     *
     * @return void
     */
    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        // Use the Config helper to read reminder days (reads from meetanshi_rewardpoints/email/expire_reminder_days)
        $reminderDays = $this->config->getExpireReminderDays();

        if (empty($reminderDays)) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $txnTable = $this->resourceConnection->getTableName('meetanshi_rewardpoints_transaction');
        $totalSent = 0;

        foreach ($reminderDays as $days) {
            $targetDate = $this->timezone->date(
                new \DateTime('+' . $days . ' days'),
            )->format('Y-m-d');

            $select = $connection->select()
                ->from($txnTable, ['transaction_id', 'account_id', 'customer_id', 'points_delta', 'expires_at'])
                ->where('status = ?', 'active')
                ->where('expires_at IS NOT NULL')
                ->where('DATE(expires_at) = ?', $targetDate)
                ->where('points_delta > 0');

            $transactions = $connection->fetchAll($select);

            foreach ($transactions as $txnRow) {
                $sent = $this->sendReminderForRow($txnRow, $days);

                if ($sent) {
                    $totalSent++;
                }
            }
        }

        if ($totalSent > 0) {
            $this->logger->info(
                sprintf('[RewardPoints] ExpirationReminders: %d reminder(s) sent.', $totalSent),
            );
        }
    }

    /**
     * Send expiration reminder for a single transaction row
     *
     * @param array<string, mixed> $txnRow
     * @param int $daysUntilExpiry
     * @return bool
     */
    private function sendReminderForRow(array $txnRow, int $daysUntilExpiry): bool
    {
        $transactionId = (int) $txnRow['transaction_id'];
        $accountId = (int) $txnRow['account_id'];
        $customerId = (int) $txnRow['customer_id'];

        try {
            $transaction = $this->transactionRepository->getById($transactionId);
            $account = $this->accountRepository->getById($accountId);

            $this->populateAccountCustomerData($account, $customerId);

            $storeId = $this->resolveStoreIdForCustomer($customerId);

            $this->emailHelper->sendExpirationReminder($account, $transaction, $daysUntilExpiry, $storeId);

            $this->logger->info(sprintf(
                '[RewardPoints] ExpirationReminder sent: customer_id=%d, transaction_id=%d, points=%d, expires=%s, days_notice=%d',
                $customerId,
                $transactionId,
                (int) $txnRow['points_delta'],
                (string) $txnRow['expires_at'],
                $daysUntilExpiry,
            ));

            return true;
        } catch (NoSuchEntityException $e) {
            $this->logger->warning(
                '[RewardPoints] ExpirationReminders: transaction or account not found',
                [
                    'transaction_id' => $transactionId,
                    'account_id'     => $accountId,
                    'message'        => $e->getMessage(),
                ],
            );
        } catch (\Exception $e) {
            $this->logger->error(
                '[RewardPoints] ExpirationReminders: error sending reminder',
                [
                    'transaction_id' => $transactionId,
                    'exception'      => $e,
                ],
            );
        }

        return false;
    }

    /**
     * Populate account object with customer email/name from customer record
     *
     * @param \Meetanshi\RewardPoints\Api\Data\AccountInterface $account
     * @param int $customerId
     * @return void
     */
    private function populateAccountCustomerData(
        \Meetanshi\RewardPoints\Api\Data\AccountInterface $account,
        int $customerId,
    ): void {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $account->setData('customer_email', $customer->getEmail());
            $account->setData('customer_firstname', $customer->getFirstname());
            $account->setData('customer_lastname', $customer->getLastname());
        } catch (\Exception $e) {
            $this->logger->warning(
                '[RewardPoints] ExpirationReminders: could not load customer data',
                ['customer_id' => $customerId, 'message' => $e->getMessage()],
            );
        }
    }

    /**
     * Resolve default store ID for the customer's website
     *
     * @param int $customerId
     * @return int
     */
    private function resolveStoreIdForCustomer(int $customerId): int
    {
        try {
            $customer = $this->customerRepository->getById($customerId);
            $websiteId = (int) $customer->getWebsiteId();
            $website = $this->storeManager->getWebsite($websiteId);
            $defaultStore = $website->getDefaultStore();

            return $defaultStore ? (int) $defaultStore->getId() : 1;
        } catch (\Exception $e) {
            return 1;
        }
    }
}

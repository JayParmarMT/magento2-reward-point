<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Meetanshi\RewardPoints\Helper\Email as EmailHelper;
use Psr\Log\LoggerInterface;

/**
 * Send balance update email when meetanshi_rewardpoints_balance_changed event fires
 */
class SendBalanceUpdateEmailObserver implements ObserverInterface
{
    /**
     * @param EmailHelper $emailHelper
     * @param AccountRepositoryInterface $accountRepository
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly EmailHelper $emailHelper,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly Config $config,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Handle balance changed event and send email notification
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            /** @var TransactionInterface $transaction */
            $transaction = $observer->getData('transaction');
            $storeId = (int) $observer->getData('store_id');

            if ($transaction === null) {
                return;
            }

            $account = $this->accountRepository->getById($transaction->getAccountId());

            if (!$account->isEnabled()) {
                return;
            }

            $this->emailHelper->sendBalanceUpdate($account, $transaction, $storeId);
        } catch (\Throwable $e) {
            $this->logger->error(
                'Meetanshi RewardPoints: SendBalanceUpdateEmailObserver error',
                ['exception' => $e->getMessage()],
            );
        }
    }
}

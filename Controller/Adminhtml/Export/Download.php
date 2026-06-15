<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Export;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Controller\ResultInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Account\CollectionFactory as AccountCollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory as TransactionCollectionFactory;

/**
 * Reward Points Export Download controller — streams a CSV of accounts or transactions
 */
class Download extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::export';

    /**
     * @param Context $context
     * @param RawFactory $resultRawFactory
     * @param AccountCollectionFactory $accountCollectionFactory
     * @param TransactionCollectionFactory $transactionCollectionFactory
     */
    public function __construct(
        Context $context,
        private readonly RawFactory $resultRawFactory,
        private readonly AccountCollectionFactory $accountCollectionFactory,
        private readonly TransactionCollectionFactory $transactionCollectionFactory,
    ) {
        parent::__construct($context);
    }

    /**
     * Stream reward points CSV download
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $type = $this->getRequest()->getParam('type', 'accounts');

        if ($type === 'transactions') {
            $filename = 'reward_points_transactions_' . date('Ymd_His') . '.csv';
            $csv = $this->buildTransactionsCsv();
        } else {
            $filename = 'reward_points_accounts_' . date('Ymd_His') . '.csv';
            $csv = $this->buildAccountsCsv();
        }

        /** @var Raw $result */
        $result = $this->resultRawFactory->create();
        $result->setHttpResponseCode(200);
        $result->setHeader('Content-Type', 'text/csv; charset=UTF-8', true);
        $result->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"', true);
        $result->setHeader('Pragma', 'public', true);
        $result->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true);
        $result->setHeader('Content-Length', (string) strlen($csv), true);
        $result->setContents($csv);

        return $result;
    }

    /**
     * Build accounts CSV string
     *
     * @return string
     */
    private function buildAccountsCsv(): string
    {
        $collection = $this->accountCollectionFactory->create();
        $collection->getSelect()->joinLeft(
            ['ce' => $collection->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            ['customer_email' => 'ce.email'],
        );
        $collection->getSelect()->joinLeft(
            ['sw' => $collection->getTable('store_website')],
            'ce.website_id = sw.website_id',
            ['website_code' => 'sw.code'],
        );

        $rows = [['customer_email', 'website_code', 'points_balance', 'is_enabled']];

        foreach ($collection as $account) {
            $rows[] = [
                (string) ($account->getData('customer_email') ?? ''),
                (string) ($account->getData('website_code') ?? 'base'),
                (int) $account->getData('points_balance'),
                (int) $account->getData('is_enabled'),
            ];
        }

        return $this->buildCsv($rows);
    }

    /**
     * Build transactions CSV string
     *
     * @return string
     */
    private function buildTransactionsCsv(): string
    {
        $collection = $this->transactionCollectionFactory->create();
        $collection->getSelect()->joinLeft(
            ['ce' => $collection->getTable('customer_entity')],
            'main_table.customer_id = ce.entity_id',
            ['customer_email' => 'ce.email'],
        );
        $collection->getSelect()->joinLeft(
            ['sw' => $collection->getTable('store_website')],
            'ce.website_id = sw.website_id',
            ['website_code' => 'sw.code'],
        );

        $rows = [['customer_email', 'website_code', 'points', 'action_code', 'comment', 'status', 'created_at']];

        foreach ($collection as $tx) {
            $rows[] = [
                (string) ($tx->getData('customer_email') ?? ''),
                (string) ($tx->getData('website_code') ?? 'base'),
                (int) $tx->getData('points_delta'),
                (string) ($tx->getData('action_code') ?? ''),
                (string) ($tx->getData('comment') ?? ''),
                (string) ($tx->getData('status') ?? ''),
                (string) ($tx->getData('created_at') ?? ''),
            ];
        }

        return $this->buildCsv($rows);
    }

    /**
     * Build a CSV string from a 2D array of rows
     *
     * @param array $rows
     * @return string
     */
    private function buildCsv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '\\');
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return (string) $csv;
    }

    /**
     * Check ACL permission
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}

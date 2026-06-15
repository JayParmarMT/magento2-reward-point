<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;

/**
 * AJAX controller — returns paginated, filterable transaction data as JSON
 * for rendering client-side in the Reward Points tab.
 */
class Transactions extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    private const PAGE_SIZE = 20;
    private const ALLOWED_SORT = [
        'transaction_id', 'action_code', 'points_delta',
        'points_balance_after', 'status', 'expires_at', 'created_at',
    ];

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly ResourceConnection $resourceConnection,
    ) {
        parent::__construct($context);
    }

    /**
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $customerId = (int) $this->getRequest()->getParam('id');

        if (!$customerId) {
            return $result->setData(['error' => true, 'message' => 'Missing customer ID']);
        }

        $connection = $this->resourceConnection->getConnection();
        $table = $this->resourceConnection->getTableName('meetanshi_rewardpoints_transaction');

        // --- params ---
        $page    = max(1, (int) $this->getRequest()->getParam('p', 1));
        $sortCol = $this->getRequest()->getParam('sort', 'created_at');
        $sortDir = strtoupper((string) $this->getRequest()->getParam('dir', 'DESC')) === 'ASC' ? 'ASC' : 'DESC';
        $search  = trim((string) $this->getRequest()->getParam('q', ''));

        if (!in_array($sortCol, self::ALLOWED_SORT, true)) {
            $sortCol = 'created_at';
        }

        // --- base select ---
        $select = $connection->select()
            ->from($table)
            ->where('customer_id = ?', $customerId);

        if ($search !== '') {
            $like = '%' . addcslashes($search, '%_') . '%';
            $select->where(
                $connection->quoteInto('action_code LIKE ?', $like)
                . ' OR ' . $connection->quoteInto('status LIKE ?', $like)
                . ' OR ' . $connection->quoteInto('comment LIKE ?', $like),
            );
        }

        // total count
        $countSelect = clone $select;
        $countSelect->reset(\Magento\Framework\DB\Select::COLUMNS)->columns(new \Magento\Framework\DB\Expr('COUNT(*)'));
        $total = (int) $connection->fetchOne($countSelect);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page  = min($page, $pages);

        // paginated rows
        $select->order("{$sortCol} {$sortDir}")
               ->limit(self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE);

        $rows = $connection->fetchAll($select);

        return $result->setData([
            'error'   => false,
            'rows'    => $rows,
            'total'   => $total,
            'page'    => $page,
            'pages'   => $pages,
            'sort'    => $sortCol,
            'dir'     => $sortDir,
            'q'       => $search,
        ]);
    }

    /**
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed(self::ADMIN_RESOURCE);
    }
}

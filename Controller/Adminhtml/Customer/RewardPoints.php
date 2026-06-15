<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;

/**
 * Admin Customer Reward Points Tab Data Controller
 */
class RewardPoints extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param AccountRepositoryInterface $accountRepository
     * @param TierRepositoryInterface $tierRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
    ) {
        parent::__construct($context);
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();
        $customerId = (int) $this->getRequest()->getParam('customer_id');

        if (!$customerId) {
            return $result->setData(['error' => true, 'message' => __('Customer ID is required.')]);
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $websiteId = (int) $customer->getWebsiteId();

            try {
                $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
                $accountData = [
                    'account_id' => $account->getAccountId(),
                    'points_balance' => $account->getPointsBalance(),
                    'total_earned' => $account->getTotalEarned(),
                    'total_spent' => $account->getTotalSpent(),
                    'is_enabled' => $account->isEnabled(),
                    'is_subscribed_balance' => $account->isSubscribedBalance(),
                    'is_subscribed_expiration' => $account->isSubscribedExpiration(),
                    'current_tier_id' => $account->getCurrentTierId(),
                ];
            } catch (NoSuchEntityException) {
                $accountData = null;
            }

            $currentTierName = __('No Tier');
            $nextTierName = __('N/A');

            if ($accountData && $accountData['current_tier_id']) {
                try {
                    $tier = $this->tierRepository->getById((int) $accountData['current_tier_id']);
                    $currentTierName = $tier->getName();
                } catch (NoSuchEntityException) {
                    // Leave as default
                }
            }

            // Get recent transactions
            $sortOrder = $this->sortOrderBuilder
                ->setField('created_at')
                ->setDescendingDirection()
                ->create();

            $searchCriteria = $this->searchCriteriaBuilder
                ->addFilter('customer_id', $customerId)
                ->addSortOrder($sortOrder)
                ->setPageSize(10)
                ->setCurrentPage(1)
                ->create();

            $txnResult = $this->transactionRepository->getList($searchCriteria);
            $transactions = [];

            foreach ($txnResult->getItems() as $txn) {
                $transactions[] = [
                    'transaction_id' => $txn->getTransactionId(),
                    'action_code' => $txn->getActionCode(),
                    'points_delta' => $txn->getPointsDelta(),
                    'points_balance_after' => $txn->getPointsBalanceAfter(),
                    'status' => $txn->getStatus(),
                    'comment' => $txn->getComment(),
                    'created_at' => $txn->getCreatedAt(),
                    'expires_at' => $txn->getExpiresAt(),
                ];
            }

            return $result->setData([
                'error' => false,
                'account' => $accountData,
                'current_tier' => (string) $currentTierName,
                'next_tier' => (string) $nextTierName,
                'transactions' => $transactions,
            ]);
        } catch (\Exception $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        }
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

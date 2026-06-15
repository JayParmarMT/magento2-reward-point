<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Customer;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;

/**
 * Admin Customer Reward Points Save Controller
 */
class RewardPointsSave extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param JsonFactory $jsonFactory
     * @param CustomerRepositoryInterface $customerRepository
     * @param BalanceManagementInterface $balanceManagement
     * @param AdminSession $adminSession
     */
    public function __construct(
        Context $context,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly AdminSession $adminSession,
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
        $data = $this->getRequest()->getPostValue();
        $customerId = (int) ($data['customer_id'] ?? 0);

        if (!$customerId) {
            return $result->setData(['error' => true, 'message' => (string) __('Customer ID is required.')]);
        }

        try {
            $this->validateData($data);

            $customer = $this->customerRepository->getById($customerId);
            $websiteId = (int) $customer->getWebsiteId();

            $updatePoints = (int) $data['update_points'];
            $comment = isset($data['comment']) ? mb_substr((string) $data['comment'], 0, 500) : null;
            $expireAfterDays = isset($data['expire_after_days']) && $data['expire_after_days'] !== ''
                ? (int) $data['expire_after_days']
                : null;
            $notifyCustomer = (bool) ($data['notify_customer'] ?? false);

            $adminUser = $this->adminSession->getUser();
            $extraData = [
                'admin_user_id' => $adminUser ? (int) $adminUser->getId() : null,
                'admin_user_name' => $adminUser ? (string) $adminUser->getUserName() : null,
            ];

            if ($updatePoints > 0) {
                $transaction = $this->balanceManagement->addPoints(
                    $customerId,
                    $websiteId,
                    $updatePoints,
                    TransactionInterface::ACTION_ADMIN,
                    $comment,
                    $expireAfterDays,
                    $notifyCustomer,
                    $extraData,
                );
            } else {
                $transaction = $this->balanceManagement->subtractPoints(
                    $customerId,
                    $websiteId,
                    abs($updatePoints),
                    TransactionInterface::ACTION_ADMIN,
                    $comment,
                    $extraData,
                );
            }

            $newBalance = $this->balanceManagement->getBalance($customerId, $websiteId);

            return $result->setData([
                'error' => false,
                'message' => (string) __('Balance updated successfully.'),
                'new_balance' => $newBalance,
                'transaction_id' => $transaction->getTransactionId(),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData(['error' => true, 'message' => $e->getMessage()]);
        } catch (\Exception $e) {
            return $result->setData([
                'error' => true,
                'message' => (string) __('Something went wrong: %1', $e->getMessage()),
            ]);
        }
    }

    /**
     * Validate form data
     *
     * @param array $data
     * @return void
     * @throws LocalizedException
     */
    private function validateData(array $data): void
    {
        if (!isset($data['update_points']) || $data['update_points'] === '') {
            throw new LocalizedException(__('Points value is required.'));
        }

        $raw = $data['update_points'];

        if (strpos((string) $raw, '.') !== false) {
            throw new LocalizedException(__('Points must be an integer (no decimals allowed).'));
        }

        if ((int) $raw === 0) {
            throw new LocalizedException(__('Points value cannot be zero.'));
        }

        if (isset($data['comment']) && mb_strlen((string) $data['comment']) > 500) {
            throw new LocalizedException(__('Comment cannot exceed 500 characters.'));
        }

        if (isset($data['expire_after_days']) && $data['expire_after_days'] !== '') {
            if ((int) $data['expire_after_days'] < 0) {
                throw new LocalizedException(__('Expire after days cannot be negative.'));
            }
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

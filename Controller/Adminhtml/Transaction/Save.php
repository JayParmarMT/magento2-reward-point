<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Adminhtml\Transaction;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\Auth\Session as AdminSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\BalanceManagementInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;

/**
 * Admin Transaction Save Controller
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Meetanshi_RewardPoints::transactions';

    /**
     * @param Context $context
     * @param BalanceManagementInterface $balanceManagement
     * @param AccountRepositoryInterface $accountRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param StoreManagerInterface $storeManager
     * @param AdminSession $adminSession
     */
    public function __construct(
        Context $context,
        private readonly BalanceManagementInterface $balanceManagement,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly StoreManagerInterface $storeManager,
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
        $resultRedirect = $this->resultRedirectFactory->create();
        $post = $this->getRequest()->getPostValue();

        if (!$post) {
            return $resultRedirect->setPath('*/*/');
        }

        // Magento UI forms with root dataScope="data" nest all field values under $_POST['data'].
        // Fall back to the raw POST array for robustness (e.g. custom AJAX submissions).
        $data = (isset($post['data']) && is_array($post['data'])) ? $post['data'] : $post;

        try {
            $this->validateData($data);

            $customerId = (int) $data['customer_id'];
            $points = (int) $data['points'];
            $comment = isset($data['comment']) ? mb_substr((string) $data['comment'], 0, 500) : null;
            $notifyCustomer = (bool) ($data['notify_customer'] ?? false);

            // expire_after_days = 0 or empty means "use global config default" (null → BalanceManagement falls back).
            // A positive integer means "expire this transaction after N days specifically".
            $expireAfterDays = null;

            if (isset($data['expire_after_days']) && $data['expire_after_days'] !== '') {
                $days = (int) $data['expire_after_days'];
                $expireAfterDays = $days > 0 ? $days : null;
            }

            // Verify customer exists
            $customer = $this->customerRepository->getById($customerId);
            $websiteId = (int) $customer->getWebsiteId();

            // Get admin user info
            $adminUser = $this->adminSession->getUser();
            $adminUserId = $adminUser ? (int) $adminUser->getId() : null;
            $adminUserName = $adminUser ? (string) $adminUser->getUserName() : null;

            $extraData = [
                'admin_user_id' => $adminUserId,
                'admin_user_name' => $adminUserName,
            ];

            if ($points > 0) {
                $this->balanceManagement->addPoints(
                    $customerId,
                    $websiteId,
                    $points,
                    TransactionInterface::ACTION_ADMIN,
                    $comment,
                    $expireAfterDays,
                    $notifyCustomer,
                    $extraData,
                );
            } else {
                $this->balanceManagement->subtractPoints(
                    $customerId,
                    $websiteId,
                    abs($points),
                    TransactionInterface::ACTION_ADMIN,
                    $comment,
                    $extraData,
                );
            }

            $this->messageManager->addSuccessMessage(__('The transaction has been saved successfully.'));

            return $resultRedirect->setPath('*/*/');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $resultRedirect->setPath('*/*/add');
        } catch (\Exception $e) {
            $this->messageManager->addExceptionMessage($e, __('Something went wrong while saving the transaction.'));

            return $resultRedirect->setPath('*/*/add');
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
        if (empty($data['customer_id'])) {
            throw new LocalizedException(__('Customer ID is required.'));
        }

        if (!isset($data['points']) || $data['points'] === '' || $data['points'] === null) {
            throw new LocalizedException(__('Points value is required.'));
        }

        $rawPoints = $data['points'];

        // Reject decimals
        if (strpos((string) $rawPoints, '.') !== false) {
            throw new LocalizedException(__('Points must be an integer value (no decimals allowed).'));
        }

        $points = (int) $rawPoints;

        if ($points === 0) {
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

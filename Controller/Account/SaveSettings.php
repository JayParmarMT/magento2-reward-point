<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Save customer reward points notification preferences
 */
class SaveSettings implements HttpPostActionInterface
{
    /**
     * @param Session $customerSession
     * @param AccountRepositoryInterface $accountRepository
     * @param FormKeyValidator $formKeyValidator
     * @param JsonFactory $jsonFactory
     * @param StoreManagerInterface $storeManager
     * @param RequestInterface $request
     * @param Config $config
     */
    public function __construct(
        private readonly Session $customerSession,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly JsonFactory $jsonFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly RequestInterface $request,
        private readonly Config $config,
    ) {
    }

    /**
     * Resolve customer website ID safely.
     *
     * @return int
     */
    private function resolveWebsiteId(): int
    {
        $websiteId = (int) $this->customerSession->getCustomer()->getWebsiteId();

        if ($websiteId > 0) {
            return $websiteId;
        }

        try {
            return (int) $this->storeManager->getWebsite()->getId();
        } catch (\Exception) {
            return 1;
        }
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled()) {
            return $result->setData(['success' => false, 'message' => __('Reward Points are currently disabled.')]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData(['success' => false, 'message' => __('Please log in to update your settings.')]);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['success' => false, 'message' => __('Invalid form key. Please refresh the page and try again.')]);
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = $this->resolveWebsiteId();

            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $account->setIsSubscribedBalance((bool) $this->request->getParam('is_subscribed_balance', false));
            $account->setIsSubscribedExpiration((bool) $this->request->getParam('is_subscribed_expiration', false));
            $this->accountRepository->save($account);

            return $result->setData(['success' => true, 'message' => __('Your notification settings have been saved.')]);
        } catch (\Exception $e) {
            return $result->setData(['success' => false, 'message' => __('Unable to save settings. Please try again.')]);
        }
    }
}

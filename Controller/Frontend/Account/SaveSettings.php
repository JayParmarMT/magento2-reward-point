<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Frontend\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Save customer reward points notification settings
 */
class SaveSettings implements HttpPostActionInterface
{
    /**
     * @param Session $customerSession
     * @param JsonFactory $jsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param RequestInterface $request
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly Session $customerSession,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => __('You must be logged in to update settings.'),
            ]);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page and try again.'),
            ]);
        }

        try {
            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getOrCreate($customerId, $websiteId);

            $subscribedBalance = (bool) $this->request->getParam('is_subscribed_balance', false);
            $subscribedExpiration = (bool) $this->request->getParam('is_subscribed_expiration', false);

            $account->setIsSubscribedBalance($subscribedBalance);
            $account->setIsSubscribedExpiration($subscribedExpiration);

            $this->accountRepository->save($account);

            return $result->setData([
                'success' => true,
                'message' => __('Your notification settings have been saved.'),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: failed to save customer settings',
                ['exception' => $e],
            );

            return $result->setData([
                'success' => false,
                'message' => __('Unable to save settings. Please try again.'),
            ]);
        }
    }
}

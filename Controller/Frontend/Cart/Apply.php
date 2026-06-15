<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Frontend\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Apply reward points to cart
 */
class Apply implements HttpPostActionInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param JsonFactory $jsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param RequestInterface $request
     * @param AccountRepositoryInterface $accountRepository
     * @param CartRepositoryInterface $cartRepository
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config,
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
                'message' => __('You must be logged in to use reward points.'),
            ]);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page and try again.'),
            ]);
        }

        try {
            $pointsRequested = (int) $this->request->getParam('points', 0);

            if ($pointsRequested <= 0) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Please enter a valid number of points.'),
                ]);
            }

            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $balance = $account->getPointsBalance();

            if ($pointsRequested > $balance) {
                return $result->setData([
                    'success' => false,
                    'message' => __('You do not have enough reward points. Available: %1', $balance),
                ]);
            }

            $minPoints = $this->config->getMinSpendingPoints();

            if ($pointsRequested < $minPoints) {
                return $result->setData([
                    'success' => false,
                    'message' => __('Minimum %1 points required to redeem.', $minPoints),
                ]);
            }

            $maxPoints = $this->config->getMaxSpendingPoints();

            if ($maxPoints > 0 && $pointsRequested > $maxPoints) {
                $pointsRequested = $maxPoints;
            }

            $quote = $this->checkoutSession->getQuote();
            $quote->setData('reward_points_used', $pointsRequested);
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            $discountAmount = (float) $quote->getData('reward_points_discount');

            return $result->setData([
                'success' => true,
                'message' => __('Reward points applied successfully.'),
                'points_used' => $pointsRequested,
                'discount_amount' => $discountAmount,
                'new_balance_display' => $this->config->formatPoints($balance - $pointsRequested),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: error applying points to cart',
                ['exception' => $e],
            );

            return $result->setData([
                'success' => false,
                'message' => __('Unable to apply reward points. Please try again.'),
            ]);
        }
    }
}

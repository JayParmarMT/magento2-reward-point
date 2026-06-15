<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Quote\Api\CartRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Remove reward points from cart
 */
class Remove implements HttpPostActionInterface
{
    /**
     * @param CustomerSession $customerSession
     * @param CheckoutSession $checkoutSession
     * @param JsonFactory $jsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param RequestInterface $request
     * @param CartRepositoryInterface $cartRepository
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly CheckoutSession $checkoutSession,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly RequestInterface $request,
        private readonly CartRepositoryInterface $cartRepository,
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

        if (!$this->config->isEnabled()) {
            return $result->setData([
                'success' => false,
                'message' => __('Reward Points are currently disabled.'),
            ]);
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setData([
                'success' => false,
                'message' => __('You must be logged in.'),
            ]);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData([
                'success' => false,
                'message' => __('Invalid form key. Please refresh the page and try again.'),
            ]);
        }

        try {
            $quote = $this->checkoutSession->getQuote();
            $quote->setData('reward_points_used', 0);
            $quote->setData('reward_points_discount', 0.0);
            $quote->collectTotals();
            $this->cartRepository->save($quote);

            return $result->setData([
                'success' => true,
                'message' => __('Reward points removed from your order.'),
            ]);
        } catch (LocalizedException $e) {
            return $result->setData([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RewardPoints: error removing points from cart', ['exception' => $e]);

            return $result->setData([
                'success' => false,
                'message' => __('Unable to remove reward points. Please try again.'),
            ]);
        }
    }
}

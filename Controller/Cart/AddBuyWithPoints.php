<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Cart as CheckoutCart;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\DataObject;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Message\ManagerInterface as MessageManager;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * "Buy With Points" controller — adds product to cart with buy-with-points flag
 * and validates customer has sufficient balance.
 */
class AddBuyWithPoints implements HttpGetActionInterface
{
    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CheckoutCart $checkoutCart
     * @param ProductRepositoryInterface $productRepository
     * @param AccountRepositoryInterface $accountRepository
     * @param StoreManagerInterface $storeManager
     * @param RedirectFactory $redirectFactory
     * @param MessageManager $messageManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly CheckoutCart $checkoutCart,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly RedirectFactory $redirectFactory,
        private readonly MessageManager $messageManager,
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
        $redirect = $this->redirectFactory->create();

        if (!$this->config->isEnabled()) {
            $this->messageManager->addErrorMessage(__('Reward Points are not currently available.'));
            $redirect->setPath('/');

            return $redirect;
        }

        if (!$this->customerSession->isLoggedIn()) {
            $this->messageManager->addNoticeMessage(__('Please log in to buy products with reward points.'));
            $redirect->setPath('customer/account/login');

            return $redirect;
        }

        $productId = (int) $this->request->getParam('product', 0);

        if ($productId <= 0) {
            $this->messageManager->addErrorMessage(__('Invalid product.'));
            $redirect->setPath('/');

            return $redirect;
        }

        try {
            $product = $this->productRepository->getById(
                $productId,
                false,
                (int) $this->storeManager->getStore()->getId(),
            );

            $pointPrice = (int) $product->getData('meetanshi_rp_point_price');

            if ($pointPrice <= 0) {
                $this->messageManager->addErrorMessage(
                    __('This product is not available for purchase with reward points.'),
                );
                $redirect->setPath($product->getUrlModel()->getUrl($product));

                return $redirect;
            }

            $customerId = (int) $this->customerSession->getCustomerId();
            $websiteId = (int) $this->storeManager->getWebsite()->getId();
            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $balance = $account->getPointsBalance();

            if ($balance < $pointPrice) {
                $this->messageManager->addErrorMessage(
                    __(
                        'You need %1 points to buy this product. Your current balance is %2.',
                        $this->config->formatPoints($pointPrice),
                        $this->config->formatPoints($balance),
                    ),
                );
                $redirect->setPath($product->getUrlModel()->getUrl($product));

                return $redirect;
            }

            $requestInfo = new DataObject(['product' => $productId, 'qty' => 1]);
            $quoteItem = $this->checkoutCart->addProduct($product, $requestInfo);

            if (is_string($quoteItem)) {
                $this->messageManager->addErrorMessage($quoteItem);
                $redirect->setPath($product->getUrlModel()->getUrl($product));

                return $redirect;
            }

            $quoteItem->setData('meetanshi_rp_buy_with_points', 1);
            $this->checkoutCart->save();

            $this->messageManager->addSuccessMessage(
                __(
                    '"%1" has been added to your cart. It will be purchased using %2 points at checkout.',
                    $product->getName(),
                    $this->config->formatPoints($pointPrice),
                ),
            );

            $redirect->setPath('checkout/cart');

            return $redirect;
        } catch (NoSuchEntityException $e) {
            $this->messageManager->addErrorMessage(__('The product was not found.'));
            $redirect->setPath('/');

            return $redirect;
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            $redirect->setPath('/');

            return $redirect;
        } catch (\Exception $e) {
            $this->logger->error(
                'RewardPoints: AddBuyWithPoints error',
                ['product_id' => $productId, 'exception' => $e],
            );
            $this->messageManager->addErrorMessage(__('Unable to add product to cart. Please try again.'));
            $redirect->setPath('/');

            return $redirect;
        }
    }
}

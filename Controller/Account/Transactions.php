<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Account;

use Magento\Customer\Model\Session;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\View\Result\PageFactory;
use Meetanshi\RewardPoints\Helper\Config;

/**
 * Reward Points Transaction History
 */
class Transactions implements HttpGetActionInterface
{
    /**
     * @param Session $customerSession
     * @param PageFactory $pageFactory
     * @param RedirectFactory $redirectFactory
     * @param Config $config
     */
    public function __construct(
        private readonly Session $customerSession,
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly Config $config,
    ) {
    }

    /**
     * Execute action
     *
     * @return ResultInterface
     */
    public function execute(): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('noroute');

            return $redirect;
        }

        if (!$this->customerSession->isLoggedIn()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('customer/account/login');

            return $redirect;
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Points History'));

        return $page;
    }
}

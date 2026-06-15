<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Referral;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Stdlib\CookieManagerInterface;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Helper\Config;
use Psr\Log\LoggerInterface;

/**
 * Referral Tracking Controller — stores referral code in session and cookie
 */
class Track implements HttpGetActionInterface
{
    private const SESSION_KEY_REFERRAL_CODE = 'meetanshi_referral_code';
    private const COOKIE_NAME_REFERRAL_CODE = 'meetanshi_referral_code';
    private const DEFAULT_COOKIE_DAYS = 30;
    private const XML_PATH_REFERRAL_COOKIE_DAYS = 'meetanshi_rewardpoints/referral/cookie_days';
    private const XML_PATH_REFERRAL_REDIRECT_URL = 'meetanshi_rewardpoints/referral/redirect_url';

    /**
     * @param RequestInterface $request
     * @param CustomerSession $customerSession
     * @param CookieManagerInterface $cookieManager
     * @param CookieMetadataFactory $cookieMetadataFactory
     * @param RedirectFactory $redirectFactory
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly CookieManagerInterface $cookieManager,
        private readonly CookieMetadataFactory $cookieMetadataFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly ScopeConfigInterface $scopeConfig,
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
        if (!$this->config->isEnabled()) {
            $redirect = $this->redirectFactory->create();
            $redirect->setPath('noroute');

            return $redirect;
        }

        $code = trim((string) $this->request->getParam('code', ''));

        if (!empty($code)) {
            $this->storeReferralCode($code);
        }

        $redirectUrl = $this->getRedirectUrl();
        $redirect = $this->redirectFactory->create();
        $redirect->setUrl($redirectUrl);

        return $redirect;
    }

    /**
     * Store referral code in session and cookie
     *
     * @param string $code
     * @return void
     */
    private function storeReferralCode(string $code): void
    {
        try {
            $this->customerSession->setData(self::SESSION_KEY_REFERRAL_CODE, $code);

            $cookieDays = $this->getCookieDays();
            $metadata = $this->cookieMetadataFactory
                ->createPublicCookieMetadata()
                ->setDuration($cookieDays * 86400)
                ->setPath('/')
                ->setHttpOnly(false)
                ->setSameSite('Lax');

            $this->cookieManager->setPublicCookie(
                self::COOKIE_NAME_REFERRAL_CODE,
                $code,
                $metadata,
            );
        } catch (\Exception $e) {
            $this->logger->warning(
                'RewardPoints: Track - failed to store referral code',
                ['code' => $code, 'exception' => $e],
            );
        }
    }

    /**
     * Get cookie expiry days from config
     *
     * @return int
     */
    private function getCookieDays(): int
    {
        $days = (int) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_COOKIE_DAYS,
            ScopeInterface::SCOPE_STORE,
        );

        return $days > 0 ? $days : self::DEFAULT_COOKIE_DAYS;
    }

    /**
     * Get redirect URL from config or fall back to homepage
     *
     * @return string
     */
    private function getRedirectUrl(): string
    {
        $url = (string) $this->scopeConfig->getValue(
            self::XML_PATH_REFERRAL_REDIRECT_URL,
            ScopeInterface::SCOPE_STORE,
        );

        if (!empty($url)) {
            return $url;
        }

        try {
            return $this->storeManager->getStore()->getBaseUrl();
        } catch (\Exception $e) {
            return '/';
        }
    }
}

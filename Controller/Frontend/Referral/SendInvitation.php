<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Controller\Frontend\Referral;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\CsrfAwareActionInterface;
use Magento\Framework\App\Request\InvalidRequestException;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\Data\InvitationInterface;
use Meetanshi\RewardPoints\Helper\Email as EmailHelper;
use Meetanshi\RewardPoints\Model\InvitationFactory;
use Meetanshi\RewardPoints\Model\ReferralCodeGenerator;
use Meetanshi\RewardPoints\Model\ResourceModel\BehaviorLog\CollectionFactory as BehaviorLogCollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation as InvitationResource;
use Meetanshi\RewardPoints\Model\ResourceModel\Invitation\CollectionFactory as InvitationCollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Frontend Send Invitation Controller
 */
class SendInvitation implements HttpPostActionInterface, CsrfAwareActionInterface
{
    private const MAX_INVITATIONS_PER_MINUTE = 10;
    private const DUPLICATE_COOLDOWN_DAYS = 30;

    /**
     * @param CustomerSession $customerSession
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param FormKeyValidator $formKeyValidator
     * @param StoreManagerInterface $storeManager
     * @param ReferralCodeGenerator $referralCodeGenerator
     * @param InvitationFactory $invitationFactory
     * @param InvitationResource $invitationResource
     * @param InvitationCollectionFactory $invitationCollectionFactory
     * @param BehaviorLogCollectionFactory $behaviorLogCollectionFactory
     * @param EmailHelper $emailHelper
     * @param UrlInterface $urlBuilder
     * @param LoggerInterface $logger
     */
    public function __construct(
        private readonly CustomerSession $customerSession,
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly StoreManagerInterface $storeManager,
        private readonly ReferralCodeGenerator $referralCodeGenerator,
        private readonly InvitationFactory $invitationFactory,
        private readonly InvitationResource $invitationResource,
        private readonly InvitationCollectionFactory $invitationCollectionFactory,
        private readonly BehaviorLogCollectionFactory $behaviorLogCollectionFactory,
        private readonly EmailHelper $emailHelper,
        private readonly UrlInterface $urlBuilder,
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
            return $result->setData(['success' => false, 'message' => __('Please log in to send invitations.')]);
        }

        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setData(['success' => false, 'message' => __('Invalid form key. Please refresh and try again.')]);
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        $websiteId = (int) $this->storeManager->getWebsite()->getId();

        // Rate limit check
        if ($this->isRateLimited($customerId)) {
            return $result->setData([
                'success' => false,
                'message' => __('You have sent too many invitations recently. Please try again later.'),
            ]);
        }

        $emailsRaw = $this->request->getParam('emails', '');
        $emails = $this->parseEmails($emailsRaw);

        if (empty($emails)) {
            return $result->setData(['success' => false, 'message' => __('Please provide at least one valid email address.')]);
        }

        $selfEmail = (string) $this->customerSession->getCustomer()->getEmail();

        try {
            $referralCode = $this->referralCodeGenerator->getOrCreateCode($customerId, $websiteId);
            $sent = 0;
            $errors = [];

            $referrerName = trim(
                (string) $this->customerSession->getCustomer()->getFirstname()
                . ' '
                . (string) $this->customerSession->getCustomer()->getLastname(),
            );

            $storeId = (int) $this->storeManager->getStore()->getId();

            $referralUrl = $this->urlBuilder->getUrl(
                'rewardpoints/referral/track',
                ['_query' => ['code' => $referralCode]],
            );

            $message = (string) $this->request->getParam('message', '');

            foreach ($emails as $email) {
                if (strtolower($email) === strtolower($selfEmail)) {
                    $errors[] = (string) __('You cannot invite yourself (%1).', $email);
                    continue;
                }

                if ($this->wasRecentlyInvited($customerId, $email, $websiteId)) {
                    $errors[] = (string) __('%1 was already invited recently.', $email);
                    continue;
                }

                $invitation = $this->invitationFactory->create();
                $invitation->setReferrerCustomerId($customerId);
                $invitation->setWebsiteId($websiteId);
                $invitation->setRefereeEmail($email);
                $invitation->setReferralCode($referralCode);
                $invitation->setStatus(InvitationInterface::STATUS_PENDING);
                $invitation->setReferrerPointsEarned(0);
                $invitation->setRefereePointsEarned(0);
                $invitation->setRefereeDiscountEarned(0.0);

                $this->invitationResource->save($invitation);

                // Send invitation email to each recipient
                try {
                    $this->emailHelper->sendReferralInvitation(
                        $email,
                        $referrerName,
                        $referralUrl,
                        $message,
                        $storeId,
                    );
                } catch (\Exception $e) {
                    $this->logger->error(
                        'RewardPoints: SendInvitation email error',
                        ['email' => $email, 'exception' => $e],
                    );
                }

                $sent++;
            }

            if ($sent > 0) {
                return $result->setData([
                    'success' => true,
                    'message' => (string) __('%1 invitation(s) sent successfully.', $sent),
                    'errors' => $errors,
                ]);
            }

            return $result->setData([
                'success' => false,
                'message' => (string) __('No invitations were sent.'),
                'errors' => $errors,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('RewardPoints: SendInvitation error', ['exception' => $e]);

            return $result->setData(['success' => false, 'message' => __('An error occurred. Please try again.')]);
        }
    }

    /**
     * Create CSRF validation exception
     *
     * @param RequestInterface $request
     * @return InvalidRequestException|null
     */
    public function createCsrfValidationException(RequestInterface $request): ?InvalidRequestException
    {
        return null;
    }

    /**
     * Validate for CSRF
     *
     * @param RequestInterface $request
     * @return bool|null
     */
    public function validateForCsrf(RequestInterface $request): ?bool
    {
        return $this->formKeyValidator->validate($request);
    }

    /**
     * Parse and validate email addresses from input
     *
     * @param mixed $emailsRaw
     * @return string[]
     */
    private function parseEmails(mixed $emailsRaw): array
    {
        if (is_array($emailsRaw)) {
            $parts = $emailsRaw;
        } else {
            $parts = preg_split('/[\s,;]+/', (string) $emailsRaw);
        }

        $valid = [];

        foreach ($parts as $email) {
            $email = trim((string) $email);

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $valid[] = strtolower($email);
            }
        }

        return array_unique($valid);
    }

    /**
     * Check if customer is rate limited (max 10 invitations per minute)
     *
     * @param int $customerId
     * @return bool
     */
    private function isRateLimited(int $customerId): bool
    {
        $connection = $this->invitationResource->getConnection();
        $oneMinuteAgo = date('Y-m-d H:i:s', strtotime('-1 minute'));

        $select = $connection->select()
            ->from($this->invitationResource->getMainTable(), ['COUNT(*)'])
            ->where('referrer_customer_id = ?', $customerId)
            ->where('created_at >= ?', $oneMinuteAgo);

        $count = (int) $connection->fetchOne($select);

        return $count >= self::MAX_INVITATIONS_PER_MINUTE;
    }

    /**
     * Check if email was already invited by this customer in last 30 days
     *
     * @param int $customerId
     * @param string $email
     * @param int $websiteId
     * @return bool
     */
    private function wasRecentlyInvited(int $customerId, string $email, int $websiteId): bool
    {
        $collection = $this->invitationCollectionFactory->create();
        $collection->addFieldToFilter('referrer_customer_id', $customerId);
        $collection->addFieldToFilter('referee_email', $email);
        $collection->addFieldToFilter('website_id', $websiteId);
        $collection->addFieldToFilter(
            'created_at',
            ['gteq' => date('Y-m-d H:i:s', strtotime('-' . self::DUPLICATE_COOLDOWN_DAYS . ' days'))],
        );
        $collection->setPageSize(1);

        return $collection->getSize() > 0;
    }
}

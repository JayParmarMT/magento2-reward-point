<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\ViewModel\Account;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SortOrderBuilder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\TransactionInterface;
use Meetanshi\RewardPoints\Api\TransactionRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * ViewModel for the reward points transaction history page
 */
class Transactions implements ArgumentInterface
{
    private const DEFAULT_PAGE_SIZE = 20;

    private int $totalCount = 0;

    /**
     * @param SessionFactory $customerSessionFactory
     * @param AccountRepositoryInterface $accountRepository
     * @param TransactionRepositoryInterface $transactionRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SortOrderBuilder $sortOrderBuilder
     * @param StoreManagerInterface $storeManager
     * @param TimezoneInterface $timezone
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly SessionFactory $customerSessionFactory,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TransactionRepositoryInterface $transactionRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly SortOrderBuilder $sortOrderBuilder,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly ScopeConfigInterface $scopeConfig,
    ) {
    }

    /**
     * Get the customer session instance.
     *
     * @return Session
     */
    private function getSession(): Session
    {
        return $this->customerSessionFactory->create();
    }

    /**
     * Resolve customer website ID safely.
     *
     * @return int
     */
    private function resolveWebsiteId(): int
    {
        $websiteId = (int) $this->getSession()->getCustomer()->getWebsiteId();

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
     * Get paginated transactions for the current customer.
     *
     * Filters are passed explicitly from the template (via $block->getRequest())
     * rather than read from $this->request, because the injected RequestInterface
     * in a ViewModel may not be the HTTP request object in all contexts.
     *
     * @param int $page
     * @param int $pageSize
     * @param string $status       Filter by status ('active','pending','expired','cancelled') or ''
     * @param string $dateFrom     Y-m-d lower bound for created_at, or ''
     * @param string $dateTo       Y-m-d upper bound for created_at, or ''
     * @return TransactionInterface[]
     */
    public function getTransactions(
        int $page = 1,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        string $status = '',
        string $dateFrom = '',
        string $dateTo = '',
    ): array {
        $page = max(1, $page);

        try {
            $customerId = (int) $this->getSession()->getCustomerId();
            $websiteId  = $this->resolveWebsiteId();
            $account    = $this->accountRepository->getByCustomer($customerId, $websiteId);

            $sortOrder = $this->sortOrderBuilder
                ->setField(TransactionInterface::CREATED_AT)
                ->setDescendingDirection()
                ->create();

            $builder = $this->searchCriteriaBuilder
                ->addFilter(TransactionInterface::ACCOUNT_ID, $account->getAccountId())
                ->setSortOrders([$sortOrder])
                ->setPageSize($pageSize)
                ->setCurrentPage($page);

            if ($status !== '') {
                $builder->addFilter(TransactionInterface::STATUS, $status);
            }

            // Normalise dates: accept YYYY-MM-DD (from <input type="date">)
            $dateFrom = $this->parseLocaleDate($dateFrom);

            if ($dateFrom !== '') {
                $builder->addFilter(TransactionInterface::CREATED_AT, $dateFrom . ' 00:00:00', 'gteq');
            }

            $dateTo = $this->parseLocaleDate($dateTo);

            if ($dateTo !== '') {
                $builder->addFilter(TransactionInterface::CREATED_AT, $dateTo . ' 23:59:59', 'lteq');
            }

            $searchCriteria = $builder->create();
            $results = $this->transactionRepository->getList($searchCriteria);
            $this->totalCount = (int) $results->getTotalCount();

            return array_values($results->getItems());
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get total transaction count (populated after getTransactions call)
     *
     * @return int
     */
    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    /**
     * Get current page number.
     *
     * Accepts the value directly so callers use $block->getRequest()->getParam()
     * rather than the injected request which may not be the HTTP request.
     *
     * @param int $page
     * @return int
     */
    public function getCurrentPage(int $page = 1): int
    {
        return max(1, $page);
    }

    /**
     * Format a DB datetime string (Y-m-d H:i:s) according to the store locale
     *
     * @param string|null $dbDate
     * @return string
     */
    public function formatDate(?string $dbDate): string
    {
        if (!$dbDate) {
            return '—';
        }

        try {
            $dt = new \DateTime($dbDate);
            $intlFmt = new \IntlDateFormatter(
                $this->getLocaleCode(),
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE,
                $this->timezone->getConfigTimezone(),
            );

            return $intlFmt->format($dt->getTimestamp()) ?: $dbDate;
        } catch (\Exception) {
            return $dbDate;
        }
    }

    /**
     * Get the JS-compatible locale date format string (for the date picker)
     * Returns a format like MM/DD/YYYY, DD.MM.YYYY etc. mapped from IntlDateFormatter
     *
     * @return string  format tokens compatible with the simple JS date input
     */
    public function getLocaleDateFormat(): string
    {
        try {
            $intlFmt = new \IntlDateFormatter(
                $this->getLocaleCode(),
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE,
                $this->timezone->getConfigTimezone(),
            );

            // getPattern() returns ICU pattern e.g. "M/d/yy" or "dd.MM.yy"
            $pattern = $intlFmt->getPattern();

            // Normalise to full-year and zero-padded patterns for the placeholder
            $pattern = preg_replace('/\byy\b/', 'yyyy', $pattern ?? '');
            $pattern = preg_replace('/\bM\b/', 'MM', $pattern ?? '');
            $pattern = preg_replace('/\bd\b/', 'dd', $pattern ?? '');

            return $pattern ?: 'MM/dd/yyyy';
        } catch (\Exception) {
            return 'MM/dd/yyyy';
        }
    }

    /**
     * Parse a locale-formatted date string back to Y-m-d for use in DB queries.
     * Falls back to interpreting raw YYYY-MM-DD if no locale parsing succeeds.
     *
     * @param string $input
     * @return string  Y-m-d or '' if blank/unparseable
     */
    public function parseLocaleDate(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            return '';
        }

        // If already Y-m-d (from fallback HTML date inputs)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $input)) {
            return $input;
        }

        try {
            $intlFmt = new \IntlDateFormatter(
                $this->getLocaleCode(),
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE,
                $this->timezone->getConfigTimezone(),
            );

            $timestamp = $intlFmt->parse($input);

            if ($timestamp !== false) {
                return date('Y-m-d', $timestamp);
            }
        } catch (\Exception) {
            // fall through
        }

        // Last resort: strtotime
        $ts = strtotime($input);

        return $ts !== false ? date('Y-m-d', $ts) : '';
    }

    /**
     * Get current store locale code
     *
     * @return string
     */
    public function getLocaleCode(): string
    {
        return (string) ($this->scopeConfig->getValue(
            'general/locale/code',
            ScopeInterface::SCOPE_STORE,
        ) ?? 'en_US');
    }

    /**
     * Get human-readable status label
     *
     * @param string $status
     * @return string
     */
    public function getStatusLabel(string $status): string
    {
        $labels = [
            TransactionInterface::STATUS_PENDING => __('Pending'),
            TransactionInterface::STATUS_ACTIVE => __('Active'),
            TransactionInterface::STATUS_EXPIRED => __('Expired'),
            TransactionInterface::STATUS_CANCELLED => __('Cancelled'),
        ];

        return (string) ($labels[$status] ?? ucfirst($status));
    }

    /**
     * Get human-readable action label
     *
     * @param string $actionCode
     * @return string
     */
    public function getActionLabel(string $actionCode): string
    {
        $labels = [
            TransactionInterface::ACTION_EARN_ORDER => __('Order Earning'),
            TransactionInterface::ACTION_SPEND_ORDER => __('Order Spending'),
            TransactionInterface::ACTION_REFUND_EARN => __('Refund (Earned Points Cancelled)'),
            TransactionInterface::ACTION_REFUND_SPEND => __('Refund (Spent Points Restored)'),
            TransactionInterface::ACTION_EXPIRE => __('Points Expired'),
            TransactionInterface::ACTION_ADMIN => __('Admin Adjustment'),
            TransactionInterface::ACTION_SIGNUP => __('Sign-Up Bonus'),
            TransactionInterface::ACTION_BIRTHDAY => __('Birthday Bonus'),
            TransactionInterface::ACTION_REVIEW => __('Product Review'),
            TransactionInterface::ACTION_NEWSLETTER => __('Newsletter Subscription'),
            TransactionInterface::ACTION_REFER_SIGNUP => __('Referral Sign-Up'),
            TransactionInterface::ACTION_REFER_ORDER => __('Referral Order'),
            TransactionInterface::ACTION_INACTIVITY => __('Inactivity Deduction'),
            TransactionInterface::ACTION_ALLOCATION => __('Manual Allocation'),
            TransactionInterface::ACTION_TIER_UP => __('Tier Upgrade Bonus'),
            TransactionInterface::ACTION_TIER_DOWN => __('Tier Downgrade'),
        ];

        return (string) ($labels[$actionCode] ?? ucwords(str_replace('_', ' ', $actionCode)));
    }
}

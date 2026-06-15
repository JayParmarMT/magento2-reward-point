<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Frontend\Account;

use Magento\Customer\Model\Session;
use Magento\Customer\Model\SessionFactory;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\Collection;
use Meetanshi\RewardPoints\ViewModel\Account\Transactions as TransactionsViewModel;

/**
 * Transactions block — wires the Magento pager with a real collection.
 */
class Transactions extends Template
{
    private const PAGE_SIZE = 20;

    private ?Collection $collection = null;

    /**
     * @param Context $context
     * @param SessionFactory $sessionFactory
     * @param AccountRepositoryInterface $accountRepository
     * @param CollectionFactory $collectionFactory
     * @param StoreManagerInterface $storeManager
     * @param TransactionsViewModel $viewModel
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly SessionFactory $sessionFactory,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TransactionsViewModel $viewModel,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get the customer session.
     *
     * @return Session
     */
    private function getSession(): Session
    {
        return $this->sessionFactory->create();
    }

    /**
     * Get the filtered, paginated transaction collection.
     *
     * @return Collection
     */
    public function getCollection(): Collection
    {
        if ($this->collection !== null) {
            return $this->collection;
        }

        $collection = $this->collectionFactory->create();

        try {
            $customerId = (int) $this->getSession()->getCustomerId();
            $websiteId  = (int) $this->getSession()->getCustomer()->getWebsiteId();

            if (!$websiteId) {
                $websiteId = (int) $this->storeManager->getWebsite()->getId();
            }

            $account = $this->accountRepository->getByCustomer($customerId, $websiteId);
            $collection->addFieldToFilter('account_id', $account->getAccountId());
        } catch (\Exception) {
            // Return empty collection on error
            $collection->addFieldToFilter('transaction_id', ['null' => true]);
            $this->collection = $collection;
            return $this->collection;
        }

        // Apply status filter
        $status = (string) $this->getRequest()->getParam('status', '');
        if ($status !== '') {
            $collection->addFieldToFilter('status', $status);
        }

        // Apply date filters
        $dateFrom = $this->viewModel->parseLocaleDate(
            (string) $this->getRequest()->getParam('date_from', '')
        );
        if ($dateFrom !== '') {
            $collection->addFieldToFilter('created_at', ['gteq' => $dateFrom . ' 00:00:00']);
        }

        $dateTo = $this->viewModel->parseLocaleDate(
            (string) $this->getRequest()->getParam('date_to', '')
        );
        if ($dateTo !== '') {
            $collection->addFieldToFilter('created_at', ['lteq' => $dateTo . ' 23:59:59']);
        }

        $collection->setOrder('created_at', 'DESC');

        $this->collection = $collection;

        return $this->collection;
    }

    /**
     * Wire the pager with the collection.
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $pager = $this->getLayout()->getBlock('rewardpoints.account.transactions.pager');

        if ($pager) {
            $pager->setAvailableLimit([self::PAGE_SIZE => self::PAGE_SIZE]);
            $pager->setShowPerPage(false);
            $pager->setCollection($this->getCollection());
            $this->setChild('pager', $pager);
        }

        return $this;
    }

    /**
     * Get rendered pager HTML.
     *
     * @return string
     */
    public function getPagerHtml(): string
    {
        return $this->getChildHtml('pager');
    }

    /**
     * Get the transactions view model (for formatDate, getActionLabel etc.)
     *
     * @return TransactionsViewModel
     */
    public function getViewModel(): TransactionsViewModel
    {
        return $this->viewModel;
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\Customer\Edit\Tab;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Ui\Component\Layout\Tabs\TabInterface;
use Meetanshi\RewardPoints\Api\AccountRepositoryInterface;
use Meetanshi\RewardPoints\Api\Data\AccountInterface;
use Meetanshi\RewardPoints\Api\TierRepositoryInterface;
use Meetanshi\RewardPoints\Model\TierCalculator;

/**
 * Customer Edit Reward Points Tab Block
 */
class RewardPoints extends Template implements TabInterface
{
    /**
     * @var string
     */
    protected $_template = 'Meetanshi_RewardPoints::customer/tab/rewardpoints.phtml';

    /**
     * @param Context $context
     * @param AccountRepositoryInterface $accountRepository
     * @param TierRepositoryInterface $tierRepository
     * @param CustomerRepositoryInterface $customerRepository
     * @param TierCalculator $tierCalculator
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly AccountRepositoryInterface $accountRepository,
        private readonly TierRepositoryInterface $tierRepository,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly TierCalculator $tierCalculator,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get tab label
     *
     * @return string
     */
    public function getTabLabel(): string
    {
        return (string) __('Reward Points');
    }

    /**
     * Get tab title
     *
     * @return string
     */
    public function getTabTitle(): string
    {
        return (string) __('Reward Points');
    }

    /**
     * Check if tab can be shown
     *
     * @return bool
     */
    public function canShowTab(): bool
    {
        return true;
    }

    /**
     * Check if tab is hidden
     *
     * @return bool
     */
    public function isHidden(): bool
    {
        return false;
    }

    /**
     * Get tab class
     *
     * @return string
     */
    public function getTabClass(): string
    {
        return '';
    }

    /**
     * Get tab URL
     *
     * @return string
     */
    public function getTabUrl(): string
    {
        return '';
    }

    /**
     * Check if tab is Ajax-loaded
     *
     * @return bool
     */
    public function isAjaxLoaded(): bool
    {
        return false;
    }

    /**
     * Get current customer ID from request
     *
     * @return int|null
     */
    public function getCustomerId(): ?int
    {
        $customerId = (int) $this->getRequest()->getParam('id');

        return $customerId ?: null;
    }

    /**
     * Get reward account for current customer
     *
     * @return AccountInterface|null
     */
    public function getRewardAccount(): ?AccountInterface
    {
        $customerId = $this->getCustomerId();

        if (!$customerId) {
            return null;
        }

        try {
            $customer = $this->customerRepository->getById($customerId);
            $websiteId = (int) $customer->getWebsiteId();

            return $this->accountRepository->getByCustomer($customerId, $websiteId);
        } catch (NoSuchEntityException) {
            return null;
        }
    }

    /**
     * Get current tier name
     *
     * Falls back to a live TierCalculator lookup when current_tier_id is not yet
     * persisted on the account (e.g. before the TierRecalculate cron has run).
     *
     * @param AccountInterface $account
     * @return string
     */
    public function getCurrentTierName(AccountInterface $account): string
    {
        $tierId = $account->getCurrentTierId();

        // If account already has a stored tier, resolve its name directly
        if ($tierId) {
            try {
                $tier = $this->tierRepository->getById($tierId);

                return $tier->getName();
            } catch (NoSuchEntityException) {
                // Tier may have been deleted — fall through to live lookup
            }
        }

        // Fall back: compute eligibility live so admin always shows the correct tier
        // even before the TierRecalculate cron has run
        try {
            $customerId = $account->getCustomerId();
            $customer = $this->customerRepository->getById($customerId);
            $websiteId = (int) $customer->getWebsiteId();
            $customerGroupId = (int) $customer->getGroupId();
            $tier = $this->tierCalculator->getEligibleTier($customerId, $websiteId, $customerGroupId);

            if ($tier) {
                // Persist the discovered tier so subsequent loads are faster
                $account->setCurrentTierId((int) $tier->getTierId());
                $this->accountRepository->save($account);

                return $tier->getName();
            }
        } catch (\Exception) {
            // Silently fall through
        }

        return (string) __('No Tier');
    }

    /**
     * Get update balance save URL
     *
     * @return string
     */
    public function getSaveUrl(): string
    {
        return $this->getUrl('meetanshi_rewardpoints/customer/rewardPointsSave');
    }

    /**
     * Get form key
     *
     * @return string
     */
    public function getFormKeyValue(): string
    {
        return $this->formKey->getFormKey();
    }
}

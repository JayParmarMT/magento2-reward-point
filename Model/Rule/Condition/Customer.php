<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\Rule\Condition;

use Magento\Customer\Model\CustomerFactory;
use Magento\Customer\Model\ResourceModel\Customer as CustomerResource;
use Magento\Customer\Model\ResourceModel\Group\Collection as CustomerGroupCollection;
use Magento\Customer\Model\ResourceModel\Group\CollectionFactory as CustomerGroupCollectionFactory;
use Magento\Framework\Event\ManagerInterface;
use Magento\Newsletter\Model\SubscriberFactory;
use Magento\Quote\Model\Quote;
use Magento\Rule\Model\Condition\AbstractCondition;
use Magento\Rule\Model\Condition\Context;

/**
 * Customer attribute condition for cart and behavior earning rules
 */
class Customer extends AbstractCondition
{
    public const ATTRIBUTE_CUSTOMER_GROUP = 'customer_group_id';
    public const ATTRIBUTE_LIFETIME_SALES = 'lifetime_sales';
    public const ATTRIBUTE_LIFETIME_SPENT_POINTS = 'lifetime_spent_points';
    public const ATTRIBUTE_NUMBER_OF_ORDERS = 'number_of_orders';
    public const ATTRIBUTE_IS_NEWSLETTER = 'is_newsletter_subscriber';
    public const ATTRIBUTE_NUMBER_OF_REVIEWS = 'number_of_reviews';
    public const ATTRIBUTE_IS_REFEREE = 'is_referee';
    public const ATTRIBUTE_IS_REFERRAL = 'is_referral';

    /**
     * @param Context $context
     * @param CustomerFactory $customerFactory
     * @param CustomerResource $customerResource
     * @param SubscriberFactory $subscriberFactory
     * @param ManagerInterface $eventManager
     * @param CustomerGroupCollectionFactory $customerGroupCollectionFactory
     * @param array $data
     */
    public function __construct(
        Context $context,
        private readonly CustomerFactory $customerFactory,
        private readonly CustomerResource $customerResource,
        private readonly SubscriberFactory $subscriberFactory,
        private readonly ManagerInterface $eventManager,
        private readonly CustomerGroupCollectionFactory $customerGroupCollectionFactory,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Load attribute options
     *
     * @return static
     */
    public function loadAttributeOptions(): static
    {
        $attributes = [
            self::ATTRIBUTE_CUSTOMER_GROUP => (string) __('Customer Group'),
            self::ATTRIBUTE_LIFETIME_SALES => (string) __('Lifetime Sales Amount'),
            self::ATTRIBUTE_LIFETIME_SPENT_POINTS => (string) __('Lifetime Spent Points'),
            self::ATTRIBUTE_NUMBER_OF_ORDERS => (string) __('Number of Orders'),
            self::ATTRIBUTE_IS_NEWSLETTER => (string) __('Is Newsletter Subscriber'),
            self::ATTRIBUTE_NUMBER_OF_REVIEWS => (string) __('Number of Reviews'),
            self::ATTRIBUTE_IS_REFEREE => (string) __('Is Referee (was referred)'),
            self::ATTRIBUTE_IS_REFERRAL => (string) __('Is Referral (referred others)'),
        ];

        $this->setAttributeOption($attributes);

        return $this;
    }

    /**
     * Get input type for attribute
     *
     * @return string
     */
    public function getInputType(): string
    {
        return match ($this->getAttribute()) {
            self::ATTRIBUTE_IS_NEWSLETTER,
            self::ATTRIBUTE_IS_REFEREE,
            self::ATTRIBUTE_IS_REFERRAL => 'select',
            self::ATTRIBUTE_CUSTOMER_GROUP => 'select',
            default => 'numeric',
        };
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType(): string
    {
        return match ($this->getAttribute()) {
            self::ATTRIBUTE_IS_NEWSLETTER,
            self::ATTRIBUTE_IS_REFEREE,
            self::ATTRIBUTE_IS_REFERRAL,
            self::ATTRIBUTE_CUSTOMER_GROUP => 'select',
            default => 'text',
        };
    }

    /**
     * Get value select options for boolean/select attributes
     *
     * @return array
     */
    public function getValueSelectOptions(): array
    {
        if (!$this->hasData('value_select_options')) {
            $options = match ($this->getAttribute()) {
                self::ATTRIBUTE_IS_NEWSLETTER,
                self::ATTRIBUTE_IS_REFEREE,
                self::ATTRIBUTE_IS_REFERRAL => [
                    ['value' => '1', 'label' => __('Yes')],
                    ['value' => '0', 'label' => __('No')],
                ],
                self::ATTRIBUTE_CUSTOMER_GROUP => $this->getCustomerGroupOptions(),
                default => [],
            };

            $this->setData('value_select_options', $options);
        }

        return $this->getData('value_select_options');
    }

    /**
     * Get customer group options for select element
     *
     * @return array
     */
    private function getCustomerGroupOptions(): array
    {
        $options = [];
        /** @var CustomerGroupCollection $collection */
        $collection = $this->customerGroupCollectionFactory->create();

        foreach ($collection as $group) {
            $options[] = [
                'value' => $group->getId(),
                'label' => $group->getCustomerGroupCode(),
            ];
        }

        return $options;
    }

    /**
     * Validate condition against quote/customer
     *
     * @param \Magento\Framework\Model\AbstractModel $model
     * @return bool
     */
    public function validate(\Magento\Framework\Model\AbstractModel $model): bool
    {
        $customer = null;

        if ($model instanceof Quote) {
            $customerId = (int) $model->getCustomerId();

            if (!$customerId) {
                return false;
            }

            $customer = $this->customerFactory->create();
            $this->customerResource->load($customer, $customerId);
        } else {
            $customer = $model;
        }

        if (!$customer || !$customer->getId()) {
            return false;
        }

        $value = $this->getCustomerAttributeValue($customer);

        return $this->validateAttribute($value);
    }

    /**
     * Get customer attribute value by condition attribute code
     *
     * @param \Magento\Customer\Model\Customer $customer
     * @return mixed
     */
    private function getCustomerAttributeValue(\Magento\Customer\Model\Customer $customer): mixed
    {
        return match ($this->getAttribute()) {
            self::ATTRIBUTE_CUSTOMER_GROUP => $customer->getGroupId(),
            self::ATTRIBUTE_LIFETIME_SALES => $customer->getData('lifetime_sales') ?? 0,
            self::ATTRIBUTE_LIFETIME_SPENT_POINTS => $customer->getData('lifetime_spent_points') ?? 0,
            self::ATTRIBUTE_NUMBER_OF_ORDERS => $customer->getData('number_of_orders') ?? 0,
            self::ATTRIBUTE_IS_NEWSLETTER => $this->isNewsletterSubscriber((int) $customer->getId()),
            self::ATTRIBUTE_NUMBER_OF_REVIEWS => $customer->getData('number_of_reviews') ?? 0,
            self::ATTRIBUTE_IS_REFEREE => $customer->getData('is_referee') ?? 0,
            self::ATTRIBUTE_IS_REFERRAL => $customer->getData('is_referral') ?? 0,
            default => null,
        };
    }

    /**
     * Check if customer is newsletter subscriber
     *
     * @param int $customerId
     * @return int
     */
    private function isNewsletterSubscriber(int $customerId): int
    {
        $subscriber = $this->subscriberFactory->create()->loadByCustomerId($customerId);

        return $subscriber->isSubscribed() ? 1 : 0;
    }
}

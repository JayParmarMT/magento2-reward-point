<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

/**
 * Reward Points Account Resource Model
 */
class Account extends AbstractDb
{
    public const TABLE_NAME = 'meetanshi_rewardpoints_account';
    public const PRIMARY_KEY = 'account_id';

    /**
     * @return void
     */
    protected function _construct(): void
    {
        $this->_init(self::TABLE_NAME, self::PRIMARY_KEY);
    }

    /**
     * Load account by customer and website
     *
     * @param \Meetanshi\RewardPoints\Model\Account $object
     * @param int $customerId
     * @param int $websiteId
     * @return $this
     */
    public function loadByCustomerWebsite(
        \Meetanshi\RewardPoints\Model\Account $object,
        int $customerId,
        int $websiteId,
    ): static {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable())
            ->where('customer_id = ?', $customerId)
            ->where('website_id = ?', $websiteId);

        $data = $connection->fetchRow($select);

        if ($data) {
            $object->setData($data);
            $object->setOrigData();
            $object->setHasDataChanges(false);
        }

        return $this;
    }
}

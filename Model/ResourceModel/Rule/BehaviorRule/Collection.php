<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Model\ResourceModel\Rule\BehaviorRule;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Meetanshi\RewardPoints\Model\Rule\BehaviorRule;
use Meetanshi\RewardPoints\Model\ResourceModel\Rule\BehaviorRule as BehaviorRuleResource;

/**
 * Reward Points Behavior Rule Collection
 */
class Collection extends AbstractCollection
{
    /**
     * @return void
     */
    protected function _construct(): void
    {
        /**
         * _init Select.
         */
        
        /**
         * Behavior Rule Resource.
         */
        
        $this->_init(BehaviorRule::class, BehaviorRuleResource::class);
    }

    /**
     * Initialize select and join websites and customer groups for grid display.
     *
     * @return void
     */
/**
     * Initialize select and join websites/customer groups, and set filter map
     * to qualify ambiguous columns introduced by the subquery joins.
     *
     * @return void
     */
    protected function _initSelect(): void
    {
        parent::_initSelect();
        $this->addFilterToMap('rule_id', 'main_table.rule_id');
        $this->addFilterToMap('websites', 'rw_agg.websites');
        $this->addFilterToMap('customer_groups', 'rg_agg.customer_groups');
        $this->joinWebsitesAndGroups();
    }

    /**
     * Join websites and customer groups via GROUP_CONCAT subqueries.
     *
     * @return void
     */
    private function joinWebsitesAndGroups(): void
    {
        $connection = $this->getConnection();
        $ruleType   = 'behavior_earning';

        $websiteSubquery = $connection->select()
            ->from(
                ['rw' => $this->getTable('meetanshi_rewardpoints_rule_website')],
                ['rule_id'],
            )
            ->joinInner(
                ['sw' => $this->getTable('store_website')],
                'sw.website_id = rw.website_id AND sw.website_id != 0',
                ['websites' => new \Magento\Framework\DB\Expr("GROUP_CONCAT(DISTINCT sw.name ORDER BY sw.name SEPARATOR ', ')")],
            )
            ->where('rw.rule_type = ?', $ruleType)
            ->group('rw.rule_id');

        $groupSubquery = $connection->select()
            ->from(
                ['rg' => $this->getTable('meetanshi_rewardpoints_rule_customer_group')],
                ['rule_id'],
            )
            ->joinInner(
                ['cg' => $this->getTable('customer_group')],
                'cg.customer_group_id = rg.customer_group_id',
                ['customer_groups' => new \Magento\Framework\DB\Expr("GROUP_CONCAT(DISTINCT cg.customer_group_code ORDER BY cg.customer_group_code SEPARATOR ', ')")],
            )
            ->where('rg.rule_type = ?', $ruleType)
            ->group('rg.rule_id');

        $this->getSelect()
            ->joinLeft(
                ['rw_agg' => $websiteSubquery],
                'rw_agg.rule_id = main_table.rule_id',
                ['websites'],
            )
            ->joinLeft(
                ['rg_agg' => $groupSubquery],
                'rg_agg.rule_id = main_table.rule_id',
                ['customer_groups'],
            );
    }
}

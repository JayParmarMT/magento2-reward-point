<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Block\Adminhtml\Report;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Meetanshi\RewardPoints\Model\ResourceModel\Transaction\CollectionFactory;

/**
 * Points Spent Report block with chart data
 */
class Spent extends Template
{
    /**
     * @param Context $context
     * @param CollectionFactory $collectionFactory
     * @param TimezoneInterface $timezone
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone,
        array $data = [],
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Get spent points chart data grouped by period
     *
     * @param string $period day|week|month
     * @return array<int, array{date: string, points: int}>
     */
    public function getChartData(string $period = 'day'): array
    {
        $collection = $this->collectionFactory->create();
        $connection = $collection->getConnection();
        $tableName = $collection->getMainTable();

        $dateExpr = $this->getDateGroupExpression($period, $connection, 'created_at');

        $select = $connection->select()
            ->from(
                $tableName,
                [
                    'date' => $dateExpr,
                    'points' => new \Magento\Framework\DB\Expr('ABS(SUM(points_delta))'),
                ],
            )
            ->where('points_delta < 0')
            ->where('action_code NOT IN (?)', ['expire'])
            ->group('date')
            ->order('date ASC')
            ->limit(90);

        $rows = $connection->fetchAll($select);

        return array_map(
            fn (array $row): array => [
                'date' => $this->formatPeriodDate($row['date'], $period),
                'points' => (int) $row['points'],
            ],
            $rows,
        );
    }

    /**
     * Get total spent points
     *
     * @return int
     */
    public function getTotalSpent(): int
    {
        $collection = $this->collectionFactory->create();
        $connection = $collection->getConnection();
        $tableName = $collection->getMainTable();

        $select = $connection->select()
            ->from($tableName, [new \Magento\Framework\DB\Expr('ABS(SUM(points_delta))')])
            ->where('points_delta < 0')
            ->where('action_code NOT IN (?)', ['expire']);

        return (int) $connection->fetchOne($select);
    }

    /**
     * Format a raw SQL period string into locale-aware display format
     *
     * @param string $rawDate  e.g. "2024-03-15", "2024-12", "2024-12"
     * @param string $period   day|week|month
     * @return string
     */
    public function formatPeriodDate(string $rawDate, string $period): string
    {
        try {
            $locale = $this->timezone->getConfigTimezone();

            if ($period === 'month') {
                // e.g. "2024-03" → format as "Mar 2024"
                $dt = \DateTime::createFromFormat('Y-m', $rawDate);

                if ($dt === false) {
                    return $rawDate;
                }

                $intlFmt = new \IntlDateFormatter(
                    $this->getLocaleCode(),
                    \IntlDateFormatter::NONE,
                    \IntlDateFormatter::NONE,
                    $locale,
                    null,
                    'MMM yyyy',
                );

                return $intlFmt->format($dt->getTimestamp()) ?: $rawDate;
            }

            if ($period === 'week') {
                // e.g. "2024-12" (year-week) → show as "Week 12, 2024"
                [$year, $week] = explode('-', $rawDate, 2);

                return __('Week %1, %2', $week, $year)->render();
            }

            // day: "2024-03-15" → locale short date
            $dt = \DateTime::createFromFormat('Y-m-d', $rawDate);

            if ($dt === false) {
                return $rawDate;
            }

            $intlFmt = new \IntlDateFormatter(
                $this->getLocaleCode(),
                \IntlDateFormatter::SHORT,
                \IntlDateFormatter::NONE,
                $locale,
            );

            return $intlFmt->format($dt->getTimestamp()) ?: $rawDate;
        } catch (\Exception $e) {
            return $rawDate;
        }
    }

    /**
     * Get current locale code from Magento configuration
     *
     * @return string
     */
    private function getLocaleCode(): string
    {
        return $this->_scopeConfig->getValue(
            'general/locale/code',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE,
        ) ?? 'en_US';
    }

    /**
     * Build SQL date grouping expression
     *
     * @param string $period
     * @param \Magento\Framework\DB\Adapter\AdapterInterface $connection
     * @param string $field
     * @return \Magento\Framework\DB\Expr
     */
    private function getDateGroupExpression(
        string $period,
        \Magento\Framework\DB\Adapter\AdapterInterface $connection,
        string $field,
    ): \Magento\Framework\DB\Expr {
        return match ($period) {
            'week' => new \Magento\Framework\DB\Expr(
                "DATE_FORMAT($field, '%Y-%u')",
            ),
            'month' => new \Magento\Framework\DB\Expr(
                "DATE_FORMAT($field, '%Y-%m')",
            ),
            default => new \Magento\Framework\DB\Expr(
                "DATE($field)",
            ),
        };
    }
}

<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Spending Rate Actions Column for UI Component Listing
 */
class SpendingRateActions extends Column
{
    private const URL_PATH_EDIT = 'meetanshi_rewardpoints/spendingrate/edit';
    private const URL_PATH_DELETE = 'meetanshi_rewardpoints/spendingrate/delete';

    /**
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param UrlInterface $urlBuilder
     * @param Escaper $escaper
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = [],
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare data source with action URLs
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item['rate_id'])) {
                continue;
            }

            $rateId = $item['rate_id'];
            $title = $this->escaper->escapeHtml($item['rate_id']);

            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_EDIT,
                        ['rate_id' => $rateId],
                    ),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_DELETE,
                        ['rate_id' => $rateId],
                    ),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Spending Rate #%1', $title),
                        'message' => __('Are you sure you want to delete spending rate #%1?', $title),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}

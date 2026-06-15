<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Tier Actions Column for UI Component Listing
 */
class TierActions extends Column
{
    private const URL_PATH_EDIT = 'meetanshi_rewardpoints/tier/edit';
    private const URL_PATH_DELETE = 'meetanshi_rewardpoints/tier/delete';

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
            if (!isset($item['tier_id'])) {
                continue;
            }

            $tierId = $item['tier_id'];
            $title = $this->escaper->escapeHtml($item['name'] ?? $tierId);

            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_EDIT,
                        ['tier_id' => $tierId],
                    ),
                    'label' => __('Edit'),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl(
                        self::URL_PATH_DELETE,
                        ['tier_id' => $tierId],
                    ),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete Tier "%1"', $title),
                        'message' => __('Are you sure you want to delete tier "%1"?', $title),
                    ],
                    'post' => true,
                ],
            ];
        }

        return $dataSource;
    }
}

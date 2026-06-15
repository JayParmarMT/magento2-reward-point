<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\Component\Listing\SpendingRule\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Spending Rule Grid Actions Column
 */
class Actions extends Column
{
    private const URL_PATH_EDIT = 'meetanshi_rewardpoints/spendingrule/edit';
    private const URL_PATH_DELETE = 'meetanshi_rewardpoints/spendingrule/delete';

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
     * Prepare Data Source
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
            if (isset($item['rule_id'])) {
                $name = $this->escaper->escapeHtml($item['name'] ?? '');
                $item[$this->getData('name')] = [
                    'edit' => [
                        'href' => $this->urlBuilder->getUrl(
                            self::URL_PATH_EDIT,
                            ['rule_id' => $item['rule_id']],
                        ),
                        'label' => __('Edit'),
                    ],
                    'delete' => [
                        'href' => $this->urlBuilder->getUrl(
                            self::URL_PATH_DELETE,
                            ['rule_id' => $item['rule_id']],
                        ),
                        'label' => __('Delete'),
                        'confirm' => [
                            'title' => __('Delete "%1"', $name),
                            'message' => __('Are you sure you want to delete the "%1" spending rule?', $name),
                        ],
                        'post' => true,
                    ],
                ];
            }
        }

        return $dataSource;
    }
}

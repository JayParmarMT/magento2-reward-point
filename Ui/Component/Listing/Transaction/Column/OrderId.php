<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\Component\Listing\Transaction\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the Order ID column as a link to the order edit page.
 *
 * The column value is the sales_order entity_id (stored in the transaction row).
 * The entity_id is displayed as the link label and used for the URL.
 */
class OrderId extends Column
{
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
     * Prepare Data Source — inject HTML link into order_id field.
     *
     * @param array $dataSource
     * @return array
     */
    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $fieldName = $this->getData('name');

        foreach ($dataSource['data']['items'] as &$item) {
            $orderId = $item['order_id'] ?? null;

            if (!$orderId) {
                continue;
            }

            $url = $this->urlBuilder->getUrl('sales/order/view', ['order_id' => $orderId]);

            $item[$fieldName] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $this->escaper->escapeUrl($url),
                $this->escaper->escapeHtml((string) $orderId),
            );
        }

        return $dataSource;
    }
}

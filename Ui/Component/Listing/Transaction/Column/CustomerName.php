<?php

declare(strict_types=1);

namespace Meetanshi\RewardPoints\Ui\Component\Listing\Transaction\Column;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the Customer column as a link to the customer edit page.
 */
class CustomerName extends Column
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
     * Prepare Data Source — inject HTML link into customer_name field.
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
            $customerId   = $item['customer_id'] ?? null;
            $customerName = trim((string) ($item['customer_name'] ?? ''));

            if (!$customerId || $customerName === '') {
                continue;
            }

            $url  = $this->urlBuilder->getUrl('customer/index/edit', ['id' => $customerId]);
            $name = $this->escaper->escapeHtml($customerName);

            $item[$fieldName] = sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                $this->escaper->escapeUrl($url),
                $name,
            );
        }

        return $dataSource;
    }
}

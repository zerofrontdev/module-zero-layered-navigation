<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\ViewModel;

use Magento\Catalog\Model\Layer\Filter\FilterInterface;
use Magento\Catalog\Model\Layer\Filter\Item as FilterItem;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Zero\LayeredNavigation\Model\PriceBounds;
use Zero\LayeredNavigation\Model\Layer\Filter\Price;
use Zero\LayeredNavigation\Model\Values;

/**
 * For the filter templates (Zero theme, Magento_LayeredNavigation and
 * Magento_Swatches): chosen options, the price form, removing one chip.
 */
class Filters implements ArgumentInterface
{
    /** How many options a filter shows before "Show more". */
    public const VISIBLE_OPTIONS = 8;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly UrlInterface $url,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly LayerResolver $layerResolver,
        private readonly PriceBounds $priceBounds
    ) {
    }

    public function isChosen(FilterItem $item): bool
    {
        return (bool) $item->getData('zero_chosen');
    }

    public function isSwatchChosen(string $attributeCode, string|int $optionId): bool
    {
        return in_array((string) $optionId, Values::parse($this->request->getParam($attributeCode)), true);
    }

    public function isPrice(?FilterInterface $filter): bool
    {
        return $filter instanceof Price;
    }

    /**
     * The price form: action, the page's other parameters (hidden fields), the
     * asked range, and the slider's ends (null when all prices are equal) and
     * handles.
     *
     * @return array{action: string, hidden: array<string, string>, from: string, to: string, currency: string,
     *     min: ?float, max: ?float, low: ?float, high: ?float}
     */
    public function priceForm(): array
    {
        $hidden = [];
        foreach ($this->request->getQueryValue() ?? [] as $name => $value) {
            if (!in_array($name, ['price', 'price_from', 'price_to', 'p'], true) && is_scalar($value)) {
                $hidden[(string) $name] = (string) $value;
            }
        }

        $from = $this->request->getParam('price_from', '');
        $from = is_scalar($from) ? (string) $from : '';
        $to = $this->request->getParam('price_to', '');
        $to = is_scalar($to) ? (string) $to : '';
        $bounds = $this->priceBounds->get($this->layerResolver->get());
        [$min, $max] = $bounds ?? [null, null];

        return [
            'action' => strtok($this->url->getCurrentUrl(), '?') ?: '',
            'hidden' => $hidden,
            'from' => $from,
            'to' => $to,
            'currency' => (string) $this->priceCurrency->getCurrencySymbol(),
            'min' => $min,
            'max' => $max,
            'low' => $min === null ? null : max($min, min($max, is_numeric($from) ? (float) $from : $min)),
            'high' => $max === null ? null : min($max, max($min, is_numeric($to) ? (float) $to : $max)),
        ];
    }

    /**
     * A chosen value's chip: take only it out (attribute filters), or the price.
     */
    public function removeUrl(FilterItem $item): string
    {
        if ($item->getData('zero_remove_url')) {
            return (string) $item->getData('zero_remove_url');
        }
        if ($item->getFilter() instanceof Price) {
            return $this->url->getUrl('*/*/*', ['_current' => true, '_use_rewrite' => true, '_query' => [
                'price' => null, 'price_from' => null, 'price_to' => null, 'p' => null,
            ]]);
        }

        return (string) $item->getRemoveUrl();
    }
}

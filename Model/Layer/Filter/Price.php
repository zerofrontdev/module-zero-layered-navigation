<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Model\Layer\Filter;

use Magento\Framework\App\RequestInterface;
use Zero\LayeredNavigation\Model\Price as PriceRange;

/**
 * The price filter by the customer's own numbers (the filter's form:
 * price_from / price_to, in the store view's currency), or Magento's own
 * ranges (price=100-500, in the base currency).
 */
class Price extends \Magento\CatalogSearch\Model\Layer\Filter\Price
{
    /** @var array{0: ?float, 1: ?float}|null */
    private ?array $range = null;

    public function apply(RequestInterface $request)
    {
        $this->range = PriceRange::range($request);
        if ($this->range === null) {
            return $this;
        }
        [$from, $to] = $this->range;
        // The form's numbers are in the store view's currency; the index is in the base one.
        $rate = (float) $this->getCurrencyRate() ?: 1.0;
        if ($request->getParam('price_from') !== null || $request->getParam('price_to') !== null) {
            $from = $from === null ? null : $from / $rate;
            $to = $to === null ? null : $to / $rate;
            $this->range = [$from, $to];
        }
        $this->getLayer()->getProductCollection()->addFieldToFilter('price', ['from' => $from, 'to' => $to]);
        $this->getLayer()->getState()->addFilter(
            $this->_createItem($this->_renderRangeLabel($from ?? 0, $to ?? ''), ($from ?? '') . '-' . ($to ?? ''))
        );

        return $this;
    }

    /**
     * The range applied, in the base currency.
     *
     * @return array{0: ?float, 1: ?float}|null
     */
    public function getChosenRange(): ?array
    {
        return $this->range;
    }
}

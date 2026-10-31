<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Test\Unit\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Item;
use Zero\LayeredNavigation\Model\Layer\Filter\Price;

/**
 * Zero's price filter with the layer, the currency rate and the chip item
 * taken out of the object manager's hands.
 */
class PriceFilterProbe extends Price
{
    public Layer $layer;

    /** @var array<int, array{0: mixed, 1: mixed}> */
    public array $labelArgs = [];

    /** @var array<int, array{0: mixed, 1: mixed}> */
    public array $chips = [];

    public function getLayer()
    {
        return $this->layer;
    }

    public function getCurrencyRate()
    {
        return 1.0;
    }

    protected function _renderRangeLabel($fromPrice, $toPrice, $isLast = false)
    {
        $this->labelArgs[] = [$fromPrice, $toPrice];

        return parent::_renderRangeLabel($fromPrice, $toPrice, $isLast);
    }

    protected function _createItem($label, $value, $count = 0)
    {
        $this->chips[] = [$label, $value];

        return (new \ReflectionClass(Item::class))->newInstanceWithoutConstructor();
    }
}

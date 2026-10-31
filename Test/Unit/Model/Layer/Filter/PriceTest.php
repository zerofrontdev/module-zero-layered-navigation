<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Test\Unit\Model\Layer\Filter;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\State;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\CatalogSearch\Model\Layer\Filter\Price as CatalogSearchPrice;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use PHPUnit\Framework\TestCase;

class PriceTest extends TestCase
{
    /**
     * The filter with no constructor, Magento's own range label (its price
     * currency formats "$10.00"), and the layer and chip creation recorded.
     *
     * @param array<string, string> $params
     * @return array{0: PriceFilterProbe, 1: Item}
     */
    private function apply(array $params): array
    {
        $probe = (new \ReflectionClass(PriceFilterProbe::class))->newInstanceWithoutConstructor();
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('format')->willReturnCallback(static fn ($amount) => '$' . number_format((float) $amount, 2));
        (new \ReflectionProperty(CatalogSearchPrice::class, 'priceCurrency'))->setValue($probe, $priceCurrency);

        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnSelf();
        $added = [];
        $state = $this->createStub(State::class);
        $state->method('addFilter')->willReturnCallback(static function ($item) use (&$added, &$state) {
            $added[] = $item;
            return $state;
        });
        $layer = $this->createStub(Layer::class);
        $layer->method('getProductCollection')->willReturn($collection);
        $layer->method('getState')->willReturn($state);
        $probe->layer = $layer;

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(static fn ($name) => $params[$name] ?? null);
        $probe->apply($request);
        $this->assertCount(1, $added);

        return [$probe, $added[0]];
    }

    public function testFromOnlyChipSaysAndAbove(): void
    {
        [$probe] = $this->apply(['price_from' => '10']);

        $this->assertSame([[10.0, '']], $probe->labelArgs);
        $this->assertSame('$10.00 and above', (string) $probe->chips[0][0]);
        $this->assertStringNotContainsString(' - ', (string) $probe->chips[0][0]);
        $this->assertSame('10-', $probe->chips[0][1]);
    }

    public function testMagentosFromOnlyRangeSaysAndAbove(): void
    {
        [$probe] = $this->apply(['price' => '100-']);

        $this->assertSame([[100.0, '']], $probe->labelArgs);
        $this->assertSame('$100.00 and above', (string) $probe->chips[0][0]);
    }

    public function testToOnlyChipKeepsZeroToX(): void
    {
        [$probe] = $this->apply(['price_to' => '50']);

        $this->assertSame([[0, 50.0]], $probe->labelArgs);
        $this->assertSame('$0.00 - $49.99', (string) $probe->chips[0][0]);
        $this->assertSame('-50', $probe->chips[0][1]);
    }

    public function testBothEndsChip(): void
    {
        [$probe] = $this->apply(['price_from' => '20', 'price_to' => '40']);

        $this->assertSame('$20.00 - $39.99', (string) $probe->chips[0][0]);
        $this->assertSame([20.0, 40.0], $probe->getChosenRange());
    }
}

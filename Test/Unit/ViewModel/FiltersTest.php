<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Test\Unit\ViewModel;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Resolver as LayerResolver;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\UrlInterface;
use PHPUnit\Framework\TestCase;
use Zero\LayeredNavigation\Model\PriceBounds;
use Zero\LayeredNavigation\ViewModel\Filters;

class FiltersTest extends TestCase
{
    /**
     * @param array<string, mixed> $query
     */
    private function priceForm(array $query, ?array $bounds = [10.0, 100.0]): array
    {
        $request = $this->createStub(Http::class);
        $request->method('getQueryValue')->willReturn($query);
        $request->method('getParam')->willReturnCallback(
            static fn ($name, $default = null) => $query[$name] ?? $default
        );
        $url = $this->createStub(UrlInterface::class);
        $url->method('getCurrentUrl')->willReturn('https://shop.test/gear/bags.html?' . http_build_query($query));
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('getCurrencySymbol')->willReturn('$');
        $layerResolver = $this->createStub(LayerResolver::class);
        $layerResolver->method('get')->willReturn($this->createStub(Layer::class));
        $priceBounds = $this->createStub(PriceBounds::class);
        $priceBounds->method('get')->willReturn($bounds);

        return (new Filters($request, $url, $priceCurrency, $layerResolver, $priceBounds))->priceForm();
    }

    public function testNonScalarPriceFieldsAreEmpty(): void
    {
        $form = $this->priceForm([
            'price_from' => ['1'],
            'price_to' => ['a' => ['b' => '2']],
            'color' => ['a' => ['b' => '1']],
            'q' => 'bag',
        ]);

        $this->assertSame('', $form['from']);
        $this->assertSame('', $form['to']);
        $this->assertSame(['q' => 'bag'], $form['hidden']);
        $this->assertSame('https://shop.test/gear/bags.html', $form['action']);
        $this->assertSame([10.0, 100.0, 10.0, 100.0], [$form['min'], $form['max'], $form['low'], $form['high']]);
    }

    public function testScalarPriceFieldsAreKeptAndHandlesStayInsideTheBounds(): void
    {
        $form = $this->priceForm(['price_from' => '1e999', 'price_to' => '5']);

        $this->assertSame('1e999', $form['from']);
        $this->assertSame('5', $form['to']);
        $this->assertSame(100.0, $form['low']);
        $this->assertSame(10.0, $form['high']);
    }

    public function testNoBoundsNoHandles(): void
    {
        $form = $this->priceForm(['price_from' => '20'], null);

        $this->assertSame('20', $form['from']);
        $this->assertNull($form['min']);
        $this->assertNull($form['max']);
        $this->assertNull($form['low']);
        $this->assertNull($form['high']);
    }
}

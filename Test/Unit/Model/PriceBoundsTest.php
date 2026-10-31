<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Test\Unit\Model;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\Filter\Item;
use Magento\Catalog\Model\Layer\State;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute as EavAttribute;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Directory\Model\Currency;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;
use Zero\LayeredNavigation\Model\FacetCounts;
use Zero\LayeredNavigation\Model\Layer\Filter\Attribute;
use Zero\LayeredNavigation\Model\Layer\Filter\Price as PriceFilter;
use Zero\LayeredNavigation\Model\PriceBounds;

class PriceBoundsTest extends TestCase
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $userParams
     */
    private function request(array $params = [], array $userParams = []): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            static fn ($name, $default = null) => $params[$name] ?? $default
        );
        $request->method('getUserParam')->willReturnCallback(
            static fn ($name, $default = null) => $userParams[$name] ?? $default
        );

        return $request;
    }

    /**
     * A category layer; $chosen: attribute code => chosen values, in the
     * state's own order, and one price chip (not an attribute filter).
     *
     * @param array<string, string[]> $chosen
     */
    private function layer(array $chosen = [], int $categoryId = 12): Layer
    {
        $items = [];
        foreach ($chosen as $code => $values) {
            $attribute = $this->createStub(EavAttribute::class);
            $attribute->method('getAttributeCode')->willReturn($code);
            $filter = $this->createStub(Attribute::class);
            $filter->method('getAttributeModel')->willReturn($attribute);
            $filter->method('getChosenValues')->willReturn($values);
            $item = $this->createStub(Item::class);
            $item->method('getFilter')->willReturn($filter);
            $items[] = $item;
        }
        $priceItem = $this->createStub(Item::class);
        $priceItem->method('getFilter')->willReturn($this->createStub(PriceFilter::class));
        $items[] = $priceItem;

        $state = $this->createStub(State::class);
        $state->method('getFilters')->willReturn($items);
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn($categoryId);
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn(1);
        $layer = $this->createStub(Layer::class);
        $layer->method('getState')->willReturn($state);
        $layer->method('getCurrentCategory')->willReturn($category);
        $layer->method('getCurrentStore')->willReturn($store);

        return $layer;
    }

    private function collection(int $size, float $min, float $max, array &$calls = []): Collection
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getSize')->willReturnCallback(static function () use (&$calls, $size) {
            $calls[] = 'getSize';
            return $size;
        });
        $collection->method('getMinPrice')->willReturnCallback(static function () use (&$calls, $min) {
            $calls[] = 'getMinPrice';
            return $min;
        });
        $collection->method('getMaxPrice')->willReturnCallback(static function () use (&$calls, $max) {
            $calls[] = 'getMaxPrice';
            return $max;
        });

        return $collection;
    }

    private function priceCurrency(): PriceCurrencyInterface
    {
        $currency = $this->createStub(Currency::class);
        $currency->method('getCode')->willReturn('USD');
        $priceCurrency = $this->createStub(PriceCurrencyInterface::class);
        $priceCurrency->method('getCurrency')->willReturn($currency);

        return $priceCurrency;
    }

    private function cacheState(bool $enabled = true): CacheState
    {
        $state = $this->createStub(CacheState::class);
        $state->method('isEnabled')->willReturn($enabled);

        return $state;
    }

    private function bounds(
        FacetCounts $facetCounts,
        CacheInterface $cache,
        Http $request,
        ?PriceCurrencyInterface $priceCurrency = null,
        bool $cacheEnabled = true
    ): PriceBounds {
        $httpContext = $this->createStub(HttpContext::class);
        $httpContext->method('getValue')->willReturn(0);

        return new PriceBounds(
            $facetCounts,
            $priceCurrency ?? $this->priceCurrency(),
            $cache,
            $this->cacheState($cacheEnabled),
            $httpContext,
            $request
        );
    }

    public function testBoundsComeFromTheFilteredCollectionAfterItsFilters(): void
    {
        $calls = [];
        $layer = $this->layer(['size' => ['167'], 'color' => ['49', '50']]);
        $facetCounts = $this->createMock(FacetCounts::class);
        // The page's own attribute filters (sorted by code, the price chip left out), no price range.
        $facetCounts->expects($this->once())->method('collection')
            ->with($layer, ['color' => ['49', '50'], 'size' => ['167']], null)
            ->willReturn($this->collection(7, 43.2, 91.5, $calls));

        $result = $this->bounds($facetCounts, $this->createStub(CacheInterface::class), $this->request())->get($layer);

        $this->assertSame([43.0, 92.0], $result);
        // The statistics clone the select as it stands: the count (which applies the search and filters) comes first.
        $this->assertSame('getSize', $calls[0]);
        $this->assertSame(['getSize', 'getMinPrice', 'getMaxPrice'], array_values(array_unique($calls)));
    }

    public function testNoSecondConversion(): void
    {
        $priceCurrency = $this->createMock(PriceCurrencyInterface::class);
        $currency = $this->createStub(Currency::class);
        $currency->method('getCode')->willReturn('EUR');
        $priceCurrency->method('getCurrency')->willReturn($currency);
        $priceCurrency->expects($this->never())->method('convert');
        $priceCurrency->expects($this->never())->method('convertAndRound');
        $facetCounts = $this->createStub(FacetCounts::class);
        $facetCounts->method('collection')->willReturn($this->collection(3, 39.12, 64.6));

        $result = $this->bounds($facetCounts, $this->createStub(CacheInterface::class), $this->request(), $priceCurrency)
            ->get($this->layer());

        $this->assertSame([39.0, 65.0], $result);
    }

    public function testKeyChangesWithCatAndFixed(): void
    {
        $keys = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(static function ($key) use (&$keys) {
            $keys[] = $key;
            return false;
        });
        $facetCounts = $this->createStub(FacetCounts::class);
        $facetCounts->method('collection')->willReturn($this->collection(3, 10.0, 20.0));
        $requests = [
            'plain' => $this->request(),
            'cat' => $this->request(['cat' => '5']),
            'fixed' => $this->request([], ['zero_fixed' => ['color' => '50', 'brand' => '7']]),
            'fixed, other order' => $this->request([], ['zero_fixed' => ['brand' => '7', 'color' => '50']]),
            // From the address only: not a fixed filter, same page as the plain one.
            'fixed in the query' => $this->request(['zero_fixed' => ['color' => '50']]),
        ];
        foreach ($requests as $request) {
            $this->bounds($facetCounts, $cache, $request)->get($this->layer());
        }

        $this->assertCount(5, $keys);
        [$plain, $cat, $fixed, $reordered, $query] = $keys;
        $this->assertStringStartsWith('zero_price_bounds_', $plain);
        $this->assertNotSame($plain, $cat);
        $this->assertNotSame($plain, $fixed);
        $this->assertNotSame($cat, $fixed);
        $this->assertSame($fixed, $reordered);
        $this->assertSame($plain, $query);
    }

    public function testKeyIgnoresANonScalarQuery(): void
    {
        $keys = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(static function ($key) use (&$keys) {
            $keys[] = $key;
            return false;
        });
        $facetCounts = $this->createStub(FacetCounts::class);
        $facetCounts->method('collection')->willReturn($this->collection(3, 10.0, 20.0));

        $this->bounds($facetCounts, $cache, $this->request(['q' => ['x']]))->get($this->layer());
        $this->bounds($facetCounts, $cache, $this->request())->get($this->layer());

        $this->assertSame($keys[1], $keys[0]);
    }

    public function testNothingToSlideIsNotCached(): void
    {
        foreach (['no products' => [0, 0.0, 0.0], 'one price' => [4, 10.0, 10.0]] as $case => [$size, $min, $max]) {
            $cache = $this->createMock(CacheInterface::class);
            $cache->expects($this->once())->method('load')->willReturn(false);
            $cache->expects($this->never())->method('save');
            $facetCounts = $this->createStub(FacetCounts::class);
            $facetCounts->method('collection')->willReturn($this->collection($size, $min, $max));

            $this->assertNull($this->bounds($facetCounts, $cache, $this->request())->get($this->layer()), $case);
        }
    }

    public function testBoundsAreSavedWithTheCategoryTag(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects($this->once())->method('save')->with(
            '[24,74]',
            $this->stringStartsWith('zero_price_bounds_'),
            ['cat_c_p_12', 'BLOCK_HTML'],
            3600
        );
        $facetCounts = $this->createStub(FacetCounts::class);
        $facetCounts->method('collection')->willReturn($this->collection(9, 24.0, 73.01));

        $this->assertSame([24.0, 74.0], $this->bounds($facetCounts, $cache, $this->request())->get($this->layer()));
    }

    public function testCachedValueIsUsed(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('load')->willReturn('[24,74]');
        $cache->expects($this->never())->method('save');
        $facetCounts = $this->createMock(FacetCounts::class);
        $facetCounts->expects($this->never())->method('collection');
        $bounds = $this->bounds($facetCounts, $cache, $this->request());

        $this->assertSame([24.0, 74.0], $bounds->get($this->layer()));
        // Kept for the rest of the request: no second cache read.
        $this->assertSame([24.0, 74.0], $bounds->get($this->layer()));
    }

    public function testAnOldCachedNullIsComputedAgain(): void
    {
        foreach (['null', '[]', '[5]', 'not json'] as $stored) {
            $cache = $this->createMock(CacheInterface::class);
            $cache->method('load')->willReturn($stored);
            $cache->expects($this->once())->method('save');
            $facetCounts = $this->createMock(FacetCounts::class);
            $facetCounts->expects($this->once())->method('collection')->willReturn($this->collection(2, 10.0, 20.0));

            $this->assertSame(
                [10.0, 20.0],
                $this->bounds($facetCounts, $cache, $this->request())->get($this->layer()),
                $stored
            );
        }
    }

    public function testBlockCacheOffNeitherReadsNorSaves(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('load');
        $cache->expects($this->never())->method('save');
        $facetCounts = $this->createStub(FacetCounts::class);
        $facetCounts->method('collection')->willReturn($this->collection(2, 10.0, 20.0));

        $this->assertSame(
            [10.0, 20.0],
            $this->bounds($facetCounts, $cache, $this->request(), null, false)->get($this->layer())
        );
    }
}

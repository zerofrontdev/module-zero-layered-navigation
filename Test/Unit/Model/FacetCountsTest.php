<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Test\Unit\Model;

use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\ItemCollectionProviderInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;
use PHPUnit\Framework\TestCase;
use Zero\LayeredNavigation\Model\FacetCounts;

class FacetCountsTest extends TestCase
{
    public function testFixedComesFromUserParamsOnly(): void
    {
        $request = $this->createMock(Http::class);
        $request->expects($this->once())->method('getUserParam')->with('zero_fixed')
            ->willReturn(['color' => '50', 'size' => ['x'], 'material' => '', 'brand' => 7]);
        $request->expects($this->never())->method('getParam');
        $request->expects($this->never())->method('getParams');
        $request->expects($this->never())->method('getQuery');
        $request->expects($this->never())->method('getQueryValue');

        $this->assertSame(['color' => '50', 'brand' => '7'], FacetCounts::fixed($request));
    }

    public function testNotAListIsNoFixed(): void
    {
        $request = $this->createStub(Http::class);
        $request->method('getUserParam')->willReturn('50');

        $this->assertSame([], FacetCounts::fixed($request));
    }

    public function testNonHttpRequestHasNoFixed(): void
    {
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->never())->method('getParam');
        $request->expects($this->never())->method('getParams');

        $this->assertSame([], FacetCounts::fixed($request));
    }

    /**
     * The extra collection gets the controller's fixed filter, and never one
     * from the address (?zero_fixed[color]=50).
     */
    public function testCollectionAppliesOnlyServerFixedFilters(): void
    {
        foreach ([
            'from the address' => [['zero_fixed' => ['color' => '50']], [], []],
            'set by the controller' => [[], ['zero_fixed' => ['brand' => '7']], [['brand', '7']]],
        ] as $case => [$params, $userParams, $expected]) {
            $request = $this->createStub(Http::class);
            $request->method('getParam')->willReturnCallback(static fn ($name, $default = null) => $params[$name] ?? $default);
            $request->method('getUserParam')->willReturnCallback(static fn ($name, $default = null) => $userParams[$name] ?? $default);

            $filtered = [];
            $collection = $this->createStub(Collection::class);
            $collection->method('addFieldToFilter')->willReturnCallback(
                static function ($field, $condition) use (&$filtered, $collection) {
                    $filtered[] = [$field, $condition];
                    return $collection;
                }
            );
            $provider = $this->createStub(ItemCollectionProviderInterface::class);
            $provider->method('getCollection')->willReturn($collection);
            $layer = $this->createStub(Layer::class);
            $layer->method('getCurrentCategory')->willReturn($this->createStub(Category::class));
            (new \ReflectionProperty(Layer::class, 'collectionProvider'))->setValue($layer, $provider);

            $result = (new FacetCounts($request))->collection($layer, [], null);

            $this->assertSame($collection, $result, $case);
            $this->assertSame($expected, $filtered, $case);
        }
    }
}

<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Model;

use Magento\Catalog\Model\Layer;
use Magento\Catalog\Model\Layer\ItemCollectionProviderInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\RequestInterface;

/**
 * Option counts for a filter that has values chosen: as if that filter were
 * not applied, with every other one still on (so "Red" says how many red
 * products the rest of the choices leave, though "Blue" is chosen). One more
 * search request per filter with chosen values, made the way the page's own
 * is (the layer's own collection provider and filters, fixed ones too).
 */
class FacetCounts
{
    /** @var array<string, array> */
    private array $cache = [];

    public function __construct(private readonly RequestInterface $request)
    {
    }

    /**
     * @param array<string, string[]> $otherFilters The other attribute filters' chosen values, by code
     * @param array{0: ?float, 1: ?float}|null $price The price range applied, in the base currency
     * @return array<string|int, array{value: string, count: int}>
     */
    public function without(Layer $layer, string $code, array $otherFilters, ?array $price): array
    {
        if (isset($this->cache[$code])) {
            return $this->cache[$code];
        }
        unset($otherFilters[$code]);

        return $this->cache[$code] = $this->collection($layer, $otherFilters, $price)->getFacetedData($code);
    }

    /**
     * The page's products with these filters only (the category or search,
     * the attribute values given, the price range given), unloaded.
     *
     * @param array<string, string[]> $attributeFilters
     * @param array{0: ?float, 1: ?float}|null $price
     */
    public function collection(Layer $layer, array $attributeFilters, ?array $price): \Magento\Catalog\Model\ResourceModel\Product\Collection
    {
        $collection = $this->provider($layer)->getCollection($layer->getCurrentCategory());
        $layer->prepareProductCollection($collection);
        foreach (self::fixed($this->request) as $code => $value) {
            $collection->addFieldToFilter($code, $value);
        }
        foreach ($attributeFilters as $code => $values) {
            if ($values) {
                $collection->addFieldToFilter($code, count($values) === 1 ? $values[0] : $values);
            }
        }
        if ($price) {
            $collection->addFieldToFilter('price', ['from' => $price[0], 'to' => $price[1]]);
        }
        $category = (int) $this->request->getParam('cat');
        if ($category) {
            $collection->addFieldToFilter('category_ids', $category);
        }

        return $collection;
    }

    /**
     * Filters a page applies by itself, not shown nor removable (a brand's
     * page: its brand): the request's zero_fixed, attribute code => value,
     * set by the page's controller with setParam, never from the address.
     *
     * @return array<string, string>
     */
    public static function fixed(RequestInterface $request): array
    {
        $fixed = $request instanceof Http ? $request->getUserParam('zero_fixed') : null;

        if (!is_array($fixed)) {
            return [];
        }

        return array_filter(
            array_map('strval', array_filter($fixed, 'is_scalar')),
            static fn ($v) => $v !== ''
        );
    }

    /**
     * The layer's own collection provider (category, search, or another
     * layer's), so the extra request is made exactly as the page's.
     */
    private function provider(Layer $layer): ItemCollectionProviderInterface
    {
        return \Closure::bind(fn () => $this->collectionProvider, $layer, Layer::class)();
    }
}

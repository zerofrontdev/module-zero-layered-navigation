<?php
/**
 * Copyright © 2026 Roman Pasichnyk
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */

declare(strict_types=1);

namespace Zero\LayeredNavigation\Model;

use Magento\Catalog\Model\Layer;
use Magento\Customer\Model\Context as CustomerContext;
use Magento\Framework\App\Cache\StateInterface as CacheState;
use Magento\Framework\App\Cache\Type\Block as BlockCache;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Http\Context as HttpContext;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Zero\LayeredNavigation\Model\Layer\Filter\Attribute;

/**
 * The price slider's ends: cheapest and dearest of the page's products with
 * every other filter applied, from the price index, in the store currency
 * (the collection's own statistics apply the rate once), rounded outwards.
 * Block-cached for an hour (cleaned by the category's product saves): one
 * search request (category and search pages), one count and one statistics
 * query. Nothing to slide over is not cached, so such a page pays that on
 * every render.
 */
class PriceBounds
{
    /** @var array{0: float, 1: float}|null|false */
    private array|null|false $bounds = false;

    private const CACHE_LIFETIME = 3600;

    public function __construct(
        private readonly FacetCounts $facetCounts,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly CacheInterface $cache,
        private readonly CacheState $cacheState,
        private readonly HttpContext $httpContext,
        private readonly RequestInterface $request
    ) {
    }

    /**
     * @return array{0: float, 1: float}|null Null when there is nothing to slide over
     */
    public function get(Layer $layer): ?array
    {
        if ($this->bounds !== false) {
            return $this->bounds;
        }
        $filters = [];
        foreach ($layer->getState()->getFilters() as $item) {
            $filter = $item->getFilter();
            if ($filter instanceof Attribute) {
                $filters[$filter->getAttributeModel()->getAttributeCode()] = $filter->getChosenValues();
            }
        }
        ksort($filters);
        $categoryId = (int) $layer->getCurrentCategory()->getId();
        $query = $this->request->getParam('q', '');
        $fixed = FacetCounts::fixed($this->request);
        ksort($fixed);
        $cacheable = $this->cacheState->isEnabled(BlockCache::TYPE_IDENTIFIER);
        $key = 'zero_price_bounds_' . hash('sha256', json_encode([
            (int) $layer->getCurrentStore()->getId(),
            $categoryId,
            is_scalar($query) ? (string) $query : '',
            (int) $this->request->getParam('cat'),
            $filters,
            $fixed,
            (string) $this->httpContext->getValue(CustomerContext::CONTEXT_GROUP),
            (string) $this->priceCurrency->getCurrency()->getCode(),
        ]));
        if ($cacheable) {
            $cached = $this->cache->load($key);
            if (is_string($cached)) {
                $decoded = json_decode($cached, true);
                // An old entry holding null (nothing to slide over) is computed again.
                if (is_array($decoded) && isset($decoded[0], $decoded[1])) {
                    return $this->bounds = [(float) $decoded[0], (float) $decoded[1]];
                }
            }
        }
        $this->bounds = $this->compute($layer, $filters);
        if ($cacheable && $this->bounds !== null) {
            $this->cache->save(
                (string) json_encode($this->bounds),
                $key,
                ['cat_c_p_' . $categoryId, BlockCache::CACHE_TAG],
                self::CACHE_LIFETIME
            );
        }

        return $this->bounds;
    }

    /**
     * @param array<string, array<int, string>> $filters
     * @return array{0: float, 1: float}|null
     */
    private function compute(Layer $layer, array $filters): ?array
    {
        $collection = $this->facetCounts->collection($layer, $filters, null);
        // getSize() first: it applies the search result and the filters, and the
        // statistics query clones the select as it stands.
        if ((int) $collection->getSize() === 0) {
            return null;
        }
        // Already in the store currency (the collection applies its rate).
        $min = floor((float) $collection->getMinPrice());
        $max = ceil((float) $collection->getMaxPrice());

        return $max > $min ? [$min, $max] : null;
    }
}

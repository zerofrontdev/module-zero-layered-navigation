# Zero_LayeredNavigation roadmap

Last updated: 2026-10-31

## Done

- Multi-select attribute filters, facet counts without the filter's own
  choice (`Model\FacetCounts`), price slider and From/To form, "Show more".
- ZERO-REVIEW B13a (code and tests):
  - A1-3: the slider bounds come from the page's own filtered collection
    (`getSize()` first, then `getMinPrice()` / `getMaxPrice()`), not the
    whole store.
  - A1-1: no second currency conversion; the collection's statistics are
    already in the store currency. `CollectionFactory` dropped from
    `PriceBounds`' constructor.
  - A1-2: the bounds' cache key includes `cat` and the fixed filters; null
    is never cached (an old cached null is recomputed).
  - A1-12: `FacetCounts::fixed()` reads `zero_fixed` from user params only
    (`Http::getUserParam`), never the query string.
  - A1-13: a from-only price chip reads "%1 and above".
  - A1-14: non-scalar, infinite or negative price input and nested filter
    arrays are ignored (no 500). The `price` range regex ends in `\z`.
  - Tests: `PriceBoundsTest`, `FacetCountsTest`, `Layer/Filter/PriceTest`
    (with `PriceFilterProbe`), `ViewModel/FiltersTest`, `PriceTest` and
    `ValuesTest` extended; e2e `layered-navigation-bounds.spec.ts`.

## Next

1. Run `layered-navigation-bounds.spec.ts` locally (storefront project)
   after recompiling and flushing the block cache.

## Traps

- `getSize()` must run before `getMinPrice()`: the statistics query clones
  the select before `_renderFilters()`, so without it the search result and
  filters are missing.
- `getMinPrice()` / `getMaxPrice()` already multiply by the currency rate;
  never `PriceCurrency::convert()` them again.
- The e2e `zero_fixed` checks first make sure the color they use narrows the
  listing and changes the other filter's counts (skip otherwise); the
  currency check skips when the rate is too close to 1 for a second
  conversion to leave its 2-unit tolerance. Without these guards the tests
  would pass on the old code too.
- `zero_fixed` is set with `$request->setParam()` (Zero_Brands brand page);
  `getParam()` would also return a `?zero_fixed[...]` from the address.
- An uncached bounds compute costs a search request on category and search
  pages (`getSize()` runs `_renderFiltersBefore()`), plus a count and a
  statistics query. Null bounds (empty result, one price) are not cached, so
  those pages pay it on every render.
- The OpenSearch result cap (10 000 ids) applies to the bounds, as to the page.
- After deploying a change here: the block cache holds old bounds (flush it).
- `Layer\Filter\Price` has a big constructor: its unit test uses a
  subclass made with `newInstanceWithoutConstructor()` and sets the parent's
  private `priceCurrency` by reflection, so Magento's own
  `_renderRangeLabel()` runs.

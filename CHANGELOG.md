# Changelog: zerofront/module-zero-layered-navigation

## 1.3.6

- The price slider's bounds come from the page's own products with the search and every other filter applied, in the store view's currency converted once (a second conversion gave wrong bounds in a non-base currency).
- The bounds are cached per store, category, search, filters, customer group and currency.
- "From" alone reads "$10.00 and above" in the chosen filters.
- Odd input in the address (`1e999`, a negative price, `price_from[]=1`, `color[a][b]=1`) is ignored instead of causing a 500.
- Fixed filters (a brand page's brand) can only be set by the page itself: `?zero_fixed[...]` in the address changes nothing.

Earlier releases (1.3.5 and before): see the Git tags.

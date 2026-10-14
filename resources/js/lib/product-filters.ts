/** The product-list filters as the page holds them ('all' = no category). */
export interface ProductFilterState {
    search: string;
    category: string;
    priceMin: number;
    priceMax: number;
    sort: string;
    /** Bounds of the catalogue prices: a min/max equal to its bound is "no limit" and not sent. */
    priceRange: { min: number; max: number };
}

export type ProductFilterOverrides = Partial<Omit<ProductFilterState, 'priceRange'>>;

/**
 * Query parameters for GET /products (ProductsController@index): the current filters plus the overrides of the
 * change being applied; empty values are left out.
 *
 * REPRO VERSION: the logic as it is today in pages/products.tsx, copied verbatim. The category Select calls
 * handleApplyFilters(), which takes no overrides and reads `selectedCategory` from its render closure (so the new
 * category never reaches it), and the price radios / min-max inputs (products.tsx:633-654, 675-695, 708-728) build
 * their params with a hardcoded `search: ''` and `sort: 'name'`.
 */
export function buildProductFilterParams(current: ProductFilterState, overrides: ProductFilterOverrides = {}): Record<string, string | number> {
    const priceRange = current.priceRange;
    const selectedCategory = current.category;
    const newMin = overrides.priceMin ?? current.priceMin;
    const newMax = overrides.priceMax ?? current.priceMax;

    const params: Record<string, string | number> = {
        search: '',
        category: selectedCategory === 'all' ? '' : selectedCategory,
        min_price: newMin !== priceRange?.min ? newMin.toString() : '',
        max_price: newMax !== priceRange?.max ? newMax.toString() : '',
        sort: 'name',
    };

    // Remove empty parameters
    Object.keys(params).forEach((key) => {
        const value = params[key];
        if (value === '' || value === null || value === undefined) {
            delete params[key];
        }
    });

    return params;
}

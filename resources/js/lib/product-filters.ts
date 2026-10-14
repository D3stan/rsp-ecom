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
 * change being applied (the value just picked is not in the React state yet when the change applies it); empty
 * values are left out.
 */
export function buildProductFilterParams(current: ProductFilterState, overrides: ProductFilterOverrides = {}): Record<string, string> {
    const { search, category, priceMin, priceMax, sort } = { ...current, ...overrides };
    const { priceRange } = current;

    const params: Record<string, string> = {
        search,
        category: category === 'all' ? '' : category,
        min_price: priceMin !== priceRange?.min ? priceMin.toString() : '',
        max_price: priceMax !== priceRange?.max ? priceMax.toString() : '',
        sort,
    };

    return Object.fromEntries(Object.entries(params).filter(([, value]) => value !== '' && value !== null && value !== undefined));
}

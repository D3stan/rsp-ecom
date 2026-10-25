export interface ProductFilterState {
    search: string;
    category: string;
    priceMin: number;
    priceMax: number;
    sort: string;
    priceRange: { min: number; max: number };
}

export type ProductFilterOverrides = Partial<Omit<ProductFilterState, 'priceRange'>>;

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

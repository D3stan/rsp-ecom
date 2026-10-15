import { buildProductFilterParams, type ProductFilterState } from '@/lib/product-filters';
import { describe, expect, it } from 'vitest';

const state = (overrides: Partial<ProductFilterState> = {}): ProductFilterState => ({
    search: '',
    category: 'all',
    priceMin: 0,
    priceMax: 1000,
    sort: 'name',
    priceRange: { min: 0, max: 1000 },
    ...overrides,
});

describe('buildProductFilterParams', () => {
    it('applies the category just selected, not the previous one', () => {
        expect(buildProductFilterParams(state({ category: 'helmets' }), { category: 'gloves' })).toEqual({ category: 'gloves', sort: 'name' });
    });

    it('applies a category selected from "all"', () => {
        expect(buildProductFilterParams(state(), { category: 'helmets' })).toEqual({ category: 'helmets', sort: 'name' });
    });

    it('keeps search and sort when a price range is picked', () => {
        const current = state({ search: 'visor', sort: 'price', category: 'helmets' });

        expect(buildProductFilterParams(current, { priceMin: 25, priceMax: 50 })).toEqual({
            search: 'visor',
            category: 'helmets',
            min_price: '25',
            max_price: '50',
            sort: 'price',
        });
    });

    it('keeps search and sort when the custom min/max is committed', () => {
        const current = state({ search: 'visor', sort: 'newest', priceMin: 10, priceMax: 90 });

        expect(buildProductFilterParams(current)).toEqual({ search: 'visor', min_price: '10', max_price: '90', sort: 'newest' });
    });

    it('leaves out empty values: no search, "all" categories, prices at the catalogue bounds', () => {
        expect(buildProductFilterParams(state())).toEqual({ sort: 'name' });
        expect(buildProductFilterParams(state({ priceMin: 0, priceMax: 1000 }), { priceMin: 500, priceMax: 1000 })).toEqual({
            min_price: '500',
            sort: 'name',
        });
    });

    it('can clear a category back to "all"', () => {
        expect(buildProductFilterParams(state({ category: 'helmets', search: 'visor' }), { category: 'all' })).toEqual({
            search: 'visor',
            sort: 'name',
        });
    });
});

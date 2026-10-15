import { describe, expect, it } from 'vitest';
import { buildGallery, distributionRows, hasPhotos, type DisplayReview } from '../review-gallery';

function review(id: number, photoIds: number[], overrides: Partial<DisplayReview> = {}): DisplayReview {
    return {
        id,
        user: `Rider ${id}`,
        rating: 5,
        comment: 'ok',
        date: '2026-01-01',
        verified_purchase: true,
        photos: photoIds.map((pid) => ({ id: pid, url: `/u/${pid}`, thumbnail_url: `/t/${pid}`, width: 100, height: 80 })),
        ...overrides,
    };
}

describe('buildGallery', () => {
    it('flattens photos across reviews in order, keeping the review link', () => {
        const gallery = buildGallery([review(1, [10, 11]), review(2, []), review(3, [12])]);

        expect(gallery.map((p) => p.id)).toEqual([10, 11, 12]);
        expect(gallery[0]).toMatchObject({ id: 10, reviewId: 1, reviewAuthor: 'Rider 1', thumbnail_url: '/t/10' });
        expect(gallery[2].reviewId).toBe(3);
    });

    it('is empty when no review has photos', () => {
        expect(buildGallery([review(1, []), review(2, [])])).toEqual([]);
    });
});

describe('hasPhotos', () => {
    it('is true only when some review has a photo', () => {
        expect(hasPhotos([review(1, []), review(2, [5])])).toBe(true);
        expect(hasPhotos([review(1, [])])).toBe(false);
    });
});

describe('distributionRows', () => {
    it('returns rows from 5 to 1 with each star share of the total', () => {
        const rows = distributionRows({ '5': 2, '4': 1, '3': 0, '2': 0, '1': 1 }, 4);

        expect(rows.map((r) => r.star)).toEqual([5, 4, 3, 2, 1]);
        expect(rows.map((r) => r.count)).toEqual([2, 1, 0, 0, 1]);
        expect(rows.map((r) => r.percent)).toEqual([50, 25, 0, 0, 25]);
    });

    it('is all zeroes without upsetting the maths when there are no reviews', () => {
        const rows = distributionRows({ '5': 0, '4': 0, '3': 0, '2': 0, '1': 0 }, 0);

        expect(rows.every((r) => r.count === 0 && r.percent === 0)).toBe(true);
    });
});

/** Shared types and pure helpers for the product-page reviews block. */

export interface ReviewPhoto {
    id: number;
    url: string;
    thumbnail_url: string;
    width: number;
    height: number;
}

export interface DisplayReview {
    id: number;
    user: string;
    rating: number;
    comment: string;
    date: string;
    verified_purchase: boolean;
    photos: ReviewPhoto[];
}

/** One tile in the photo strip / lightbox, carrying which review it belongs to. */
export interface GalleryPhoto extends ReviewPhoto {
    reviewId: number;
    reviewAuthor: string;
}

/** Star counts, highest first, as sent by the server. */
export type RatingDistribution = Record<string, number>;

export interface DistributionRow {
    star: number;
    count: number;
    /** 0–100, share of the total review count */
    percent: number;
}

/** Flatten every review's approved photos into one ordered list for the strip and the lightbox. */
export function buildGallery(reviews: DisplayReview[]): GalleryPhoto[] {
    return reviews.flatMap((review) =>
        review.photos.map((photo) => ({
            ...photo,
            reviewId: review.id,
            reviewAuthor: review.user,
        })),
    );
}

export function hasPhotos(reviews: DisplayReview[]): boolean {
    return reviews.some((review) => review.photos.length > 0);
}

/** Rows for the distribution bars (5 down to 1), with each star's share of the total. */
export function distributionRows(distribution: RatingDistribution, total: number): DistributionRow[] {
    return [5, 4, 3, 2, 1].map((star) => {
        const count = distribution[String(star)] ?? 0;

        return {
            star,
            count,
            percent: total > 0 ? Math.round((count / total) * 100) : 0,
        };
    });
}

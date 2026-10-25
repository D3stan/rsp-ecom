
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

export interface GalleryPhoto extends ReviewPhoto {
    reviewId: number;
    reviewAuthor: string;
}

export type RatingDistribution = Record<string, number>;

export interface DistributionRow {
    star: number;
    count: number;
    percent: number;
}

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

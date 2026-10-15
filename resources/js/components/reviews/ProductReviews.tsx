import { Badge } from '@/components/ui/badge';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { useTranslation } from '@/hooks/useTranslation';
import { Link } from '@inertiajs/react';
import { BadgeCheck, ChevronDown, Star } from 'lucide-react';
import { useState } from 'react';
import ReviewLightbox from './ReviewLightbox';
import { buildGallery, distributionRows, type DisplayReview, type RatingDistribution } from './review-gallery';

interface ProductReviewsProps {
    reviews: DisplayReview[];
    average: number | null;
    count: number;
    distribution: RatingDistribution;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Where "how we verify reviews" links (FAQ or terms). */
    verifyHref: string;
}

function stars(rating: number, size = 'h-4 w-4') {
    return (
        <span className="flex" aria-hidden="true">
            {Array.from({ length: 5 }, (_, i) => (
                <Star key={i} className={`${size} ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
            ))}
        </span>
    );
}

/**
 * Product-page reviews block: rating summary with distribution bars, a strip of approved photos that opens a
 * lightbox, and the verified reviews. Expanded by default when there are photos (set by the caller).
 */
export default function ProductReviews({ reviews, average, count, distribution, open, onOpenChange, verifyHref }: ProductReviewsProps) {
    const { t, locale } = useTranslation();
    const [lightboxIndex, setLightboxIndex] = useState<number | null>(null);

    if (reviews.length === 0) return null;

    const gallery = buildGallery(reviews);
    const rows = distributionRows(distribution, count);
    const formattedAverage =
        average !== null ? new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(average) : null;

    return (
        <div id="reviews" className="mt-16 scroll-mt-24 space-y-4">
            <Collapsible open={open} onOpenChange={onOpenChange} className="rounded-lg border border-gray-300">
                <CollapsibleTrigger className="flex w-full items-center justify-between rounded-lg bg-gray-50 p-4 transition-colors hover:bg-gray-100">
                    <span className="text-lg font-semibold text-gray-900">{t('product.reviews_count', { count })}</span>
                    <ChevronDown className={`h-5 w-5 text-black transition-transform duration-200 ${open ? 'rotate-180' : ''}`} />
                </CollapsibleTrigger>
                <CollapsibleContent className="mt-2 rounded-lg p-4">
                    {/* Rating summary + distribution */}
                    {formattedAverage !== null && (
                        <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center">
                            <div className="flex flex-col items-center sm:w-40">
                                <span className="text-4xl font-bold text-gray-900">{formattedAverage}</span>
                                {stars(average ?? 0, 'h-5 w-5')}
                                <span className="mt-1 text-sm text-gray-600">{t('product.reviews_count', { count })}</span>
                            </div>
                            <ul className="flex-1 space-y-1">
                                {rows.map((row) => (
                                    <li key={row.star} className="flex items-center gap-2 text-sm">
                                        <span className="w-12 shrink-0 text-gray-600">{t('product.reviews_block.stars_n', { n: row.star })}</span>
                                        <span className="h-2 flex-1 overflow-hidden rounded-full bg-gray-200">
                                            <span className="block h-full rounded-full bg-yellow-400" style={{ width: `${row.percent}%` }} />
                                        </span>
                                        <span className="w-8 shrink-0 text-right text-gray-500">{row.count}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Photo strip */}
                    {gallery.length > 0 && (
                        <div className="mb-6">
                            <h3 className="mb-2 text-sm font-medium text-gray-900">{t('product.reviews_block.customer_photos')}</h3>
                            <ul className="flex gap-2 overflow-x-auto pb-2">
                                {gallery.map((photo, index) => (
                                    <li key={photo.id}>
                                        <button
                                            type="button"
                                            onClick={() => setLightboxIndex(index)}
                                            className="block h-20 w-20 shrink-0 overflow-hidden rounded-lg border border-gray-200 transition-colors hover:border-gray-400 focus:ring-2 focus:ring-black focus:outline-none"
                                            aria-label={t('product.reviews_block.open_photo', { n: index + 1 })}
                                        >
                                            <img
                                                src={photo.thumbnail_url}
                                                alt={t('product.reviews_block.photo_by', { name: photo.reviewAuthor })}
                                                width={photo.width}
                                                height={photo.height}
                                                loading="lazy"
                                                decoding="async"
                                                className="h-full w-full object-cover"
                                            />
                                        </button>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Transparency line (Omnibus Directive) */}
                    <p className="mb-6 text-sm text-gray-500">
                        {t('product.reviews_block.verify_note')}{' '}
                        <Link href={verifyHref} className="underline hover:text-gray-900">
                            {t('product.reviews_block.verify_link')}
                        </Link>
                    </p>

                    {/* Reviews list */}
                    <div className="space-y-6">
                        {reviews.map((review) => {
                            let galleryOffset = 0;
                            for (const r of reviews) {
                                if (r.id === review.id) break;
                                galleryOffset += r.photos.length;
                            }

                            return (
                                <div key={review.id} className="border-b border-gray-200 pb-6 last:border-b-0">
                                    <div className="mb-2 flex items-center justify-between">
                                        <span className="font-medium text-gray-900">{review.user}</span>
                                        <span className="text-sm text-gray-500">{new Date(review.date).toLocaleDateString(locale)}</span>
                                    </div>
                                    <div className="mb-2 flex items-center gap-2">
                                        {stars(review.rating)}
                                        {review.verified_purchase && (
                                            <Badge variant="secondary" className="gap-1 bg-green-100 text-green-800">
                                                <BadgeCheck className="h-3.5 w-3.5" aria-hidden="true" />
                                                {t('product.reviews_block.verified')}
                                            </Badge>
                                        )}
                                    </div>
                                    {review.comment && <p className="text-gray-700">{review.comment}</p>}
                                    {review.photos.length > 0 && (
                                        <ul className="mt-3 flex gap-2">
                                            {review.photos.map((photo, i) => (
                                                <li key={photo.id}>
                                                    <button
                                                        type="button"
                                                        onClick={() => setLightboxIndex(galleryOffset + i)}
                                                        className="block h-16 w-16 overflow-hidden rounded-md border border-gray-200 transition-colors hover:border-gray-400 focus:ring-2 focus:ring-black focus:outline-none"
                                                        aria-label={t('product.reviews_block.open_photo', { n: galleryOffset + i + 1 })}
                                                    >
                                                        <img
                                                            src={photo.thumbnail_url}
                                                            alt={t('product.reviews_block.photo_by', { name: review.user })}
                                                            width={photo.width}
                                                            height={photo.height}
                                                            loading="lazy"
                                                            decoding="async"
                                                            className="h-full w-full object-cover"
                                                        />
                                                    </button>
                                                </li>
                                            ))}
                                        </ul>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </CollapsibleContent>
            </Collapsible>

            <ReviewLightbox photos={gallery} index={lightboxIndex} onIndexChange={setLightboxIndex} />
        </div>
    );
}

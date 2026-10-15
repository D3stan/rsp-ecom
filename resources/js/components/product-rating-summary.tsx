import { useTranslation } from '@/hooks/useTranslation';
import { Star } from 'lucide-react';

interface ProductRatingSummaryProps {
    /** Average of the approved reviews, null when there are none. */
    average: number | null;
    count: number;
    /** Opens and scrolls to the reviews section. */
    onShowReviews: () => void;
}

/** Stars, average and review count under the product name; nothing when the product has no approved reviews. */
export function ProductRatingSummary({ average, count, onShowReviews }: ProductRatingSummaryProps) {
    const { t, locale } = useTranslation();

    if (average === null || count < 1) {
        return null;
    }

    const formatted = new Intl.NumberFormat(locale, { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(average);

    return (
        <button
            type="button"
            onClick={onShowReviews}
            className="mb-4 flex items-center space-x-2 text-left hover:underline"
            aria-label={`${t('product.rating_label', { rating: formatted })}, ${t('product.reviews_count', { count })}`}
        >
            {/* whole stars, rounded down like ProductCard; the exact average is shown next to them */}
            <span className="flex" aria-hidden="true">
                {Array.from({ length: 5 }, (_, i) => (
                    <Star key={i} className={`h-5 w-5 ${i < Math.floor(average) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                ))}
            </span>
            <span className="text-sm font-medium text-gray-900" aria-hidden="true">
                {formatted}
            </span>
            <span className="text-sm text-gray-600" aria-hidden="true">
                {t('product.reviews_count', { count })}
            </span>
        </button>
    );
}

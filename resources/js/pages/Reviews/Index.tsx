import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';
import { Star, Package, Calendar, MessageSquare } from 'lucide-react';
import type { Review, PaginatedData } from '@/types';

interface ReviewsIndexProps {
    reviews: PaginatedData<Review>;
}

export default function ReviewsIndex({ reviews }: ReviewsIndexProps) {
    const { t } = useTranslation();

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const renderStars = (rating: number) => {
        return Array.from({ length: 5 }, (_, i) => (
            <Star
                key={i}
                className={`h-4 w-4 ${
                    i < rating
                        ? 'fill-yellow-400 text-yellow-400'
                        : 'text-gray-300'
                }`}
            />
        ));
    };

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: t('reviews.title'),
                    href: route('reviews.index'),
                },
            ]}
        >
            <Head title={t('reviews.title')} />

            <div className="space-y-6 p-4 md:p-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{t('reviews.title')}</h1>
                        <p className="text-muted-foreground">
                            {t('reviews.description')}
                        </p>
                    </div>
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-muted-foreground">
                            {reviews.total} {reviews.total === 1 ? t('reviews.review') : t('reviews.reviews_plural')}
                        </span>
                    </div>
                </div>

                {reviews.data.length > 0 ? (
                    <>
                        {/* Reviews Grid */}
                        <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                            {reviews.data.map((review) => (
                                <Card key={review.id} className="overflow-hidden">
                                    <CardHeader>
                                        <div className="flex items-start gap-4">
                                            <div className="flex-shrink-0">
                                                {review.product.image_url ? (
                                                    <img
                                                        src={review.product.image_url}
                                                        alt={review.product.name}
                                                        className="h-16 w-16 rounded-lg object-cover"
                                                    />
                                                ) : (
                                                    <div className="h-16 w-16 rounded-lg bg-muted flex items-center justify-center">
                                                        <Package className="h-8 w-8 text-muted-foreground" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="flex-1 min-w-0">
                                                <CardTitle className="text-lg leading-tight">
                                                    <Link
                                                        href={`/products/${review.product.slug}`}
                                                        className="hover:text-primary transition-colors"
                                                    >
                                                        {review.product.name}
                                                    </Link>
                                                </CardTitle>
                                                <p className="text-sm text-muted-foreground">
                                                    {formatCurrency(review.product.price)}
                                                </p>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* Rating */}
                                        <div className="flex items-center gap-2">
                                            <div className="flex">
                                                {renderStars(review.rating)}
                                            </div>
                                            <span className="text-sm font-medium">
                                                {review.rating}/5
                                            </span>
                                        </div>

                                        {/* Comment */}
                                        {review.comment && (
                                            <div className="bg-muted/50 rounded-lg p-3">
                                                <div className="flex items-start gap-2">
                                                    <MessageSquare className="h-4 w-4 text-muted-foreground mt-0.5 flex-shrink-0" />
                                                    <p className="text-sm text-muted-foreground leading-relaxed">
                                                        "{review.comment}"
                                                    </p>
                                                </div>
                                            </div>
                                        )}

                                        {/* Status and Date */}
                                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-3 w-3" />
                                                <span>{formatDate(review.created_at)}</span>
                                            </div>
                                            <Badge 
                                                variant={review.is_approved ? "outline" : "secondary"}
                                                className="text-xs"
                                            >
                                                {review.is_approved ? t('reviews.approved') : t('reviews.pending')}
                                            </Badge>
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        {/* Pagination */}
                        {reviews.last_page > 1 && (
                            <div className="flex justify-center">
                                <div className="flex items-center gap-2">
                                    {reviews.prev_page_url && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={reviews.prev_page_url}>
                                                {t('products.prev')}
                                            </Link>
                                        </Button>
                                    )}
                                    <span className="text-sm text-muted-foreground px-3">
                                        {t('products.page_of', { 
                                            current: reviews.current_page, 
                                            total: reviews.last_page 
                                        })}
                                    </span>
                                    {reviews.next_page_url && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={reviews.next_page_url}>
                                                {t('products.next')}
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    /* Empty State */
                    <Card className="text-center py-12">
                        <CardContent>
                            <MessageSquare className="mx-auto h-16 w-16 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-medium mb-2">{t('reviews.no_reviews_title')}</h3>
                            <p className="text-muted-foreground mb-6 max-w-md mx-auto">
                                {t('reviews.no_reviews_description')}
                            </p>
                            <Button asChild>
                                <Link href={route('orders.index')}>
                                    {t('reviews.view_orders')}
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

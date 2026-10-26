import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { useMoney } from '@/hooks/use-money';
import { useTranslation } from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import type { PaginatedData, Review } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { Calendar, MessageSquare, Package, Star } from 'lucide-react';

interface ReviewsIndexProps {
    reviews: PaginatedData<Review>;
}

export default function ReviewsIndex({ reviews }: ReviewsIndexProps) {
    const { t, locale } = useTranslation();

    const formatCurrency = useMoney();

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
        });
    };

    const renderStars = (rating: number) => {
        return Array.from({ length: 5 }, (_, i) => (
            <Star key={i} className={`h-4 w-4 ${i < rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
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

            <div className="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{t('reviews.title')}</h1>
                        <p className="text-muted-foreground">{t('reviews.description')}</p>
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
                                                    <div className="flex h-16 w-16 items-center justify-center rounded-lg bg-muted">
                                                        <Package className="h-8 w-8 text-muted-foreground" />
                                                    </div>
                                                )}
                                            </div>
                                            <div className="min-w-0 flex-1">
                                                <CardTitle className="text-lg leading-tight">
                                                    <Link href={`/products/${review.product.slug}`} className="transition-colors hover:text-primary">
                                                        {review.product.name}
                                                    </Link>
                                                </CardTitle>
                                                <p className="text-sm text-muted-foreground">{formatCurrency(review.product.price)}</p>
                                            </div>
                                        </div>
                                    </CardHeader>
                                    <CardContent className="space-y-4">
                                        {/* Rating */}
                                        <div className="flex items-center gap-2">
                                            <div className="flex">{renderStars(review.rating)}</div>
                                            <span className="text-sm font-medium">{review.rating}/5</span>
                                        </div>

                                        {/* Comment */}
                                        {review.comment && (
                                            <div className="rounded-lg bg-muted/50 p-3">
                                                <div className="flex items-start gap-2">
                                                    <MessageSquare className="mt-0.5 h-4 w-4 flex-shrink-0 text-muted-foreground" />
                                                    <p className="text-sm leading-relaxed text-muted-foreground">"{review.comment}"</p>
                                                </div>
                                            </div>
                                        )}

                                        {/* Status and Date */}
                                        <div className="flex items-center justify-between text-xs text-muted-foreground">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-3 w-3" />
                                                <span>{formatDate(review.created_at)}</span>
                                            </div>
                                            <Badge variant={review.is_approved ? 'outline' : 'secondary'} className="text-xs">
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
                                            <Link href={reviews.prev_page_url}>{t('products.prev')}</Link>
                                        </Button>
                                    )}
                                    <span className="px-3 text-sm text-muted-foreground">
                                        {t('products.page_of', {
                                            current: reviews.current_page,
                                            total: reviews.last_page,
                                        })}
                                    </span>
                                    {reviews.next_page_url && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={reviews.next_page_url}>{t('products.next')}</Link>
                                        </Button>
                                    )}
                                </div>
                            </div>
                        )}
                    </>
                ) : (
                    /* Empty State */
                    <Card className="py-12 text-center">
                        <CardContent>
                            <MessageSquare className="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
                            <h3 className="mb-2 text-lg font-medium">{t('reviews.no_reviews_title')}</h3>
                            <p className="mx-auto mb-6 max-w-md text-muted-foreground">{t('reviews.no_reviews_description')}</p>
                            <Button asChild>
                                <Link href={route('orders.index')}>{t('reviews.view_orders')}</Link>
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}

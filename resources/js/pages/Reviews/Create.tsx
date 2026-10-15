import ReviewPhotoInput from '@/components/reviews/ReviewPhotoInput';
import { type PhotoLimits } from '@/components/reviews/review-photos';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useMoney } from '@/hooks/use-money';
import { useTranslation } from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { ArrowLeft, Package, Star } from 'lucide-react';
import React, { useState } from 'react';

interface Product {
    id: number;
    name: string;
    image_url?: string;
    price: number;
}

interface ReviewCreatePageProps {
    product: Product;
    photoLimits: PhotoLimits;
}

type ReviewForm = {
    product_id: number;
    rating: number;
    comment: string;
    photos: File[];
};

const ReviewCreatePage: React.FC<ReviewCreatePageProps> = ({ product, photoLimits }) => {
    const { t } = useTranslation();
    const [hoveredRating, setHoveredRating] = useState(0);

    // The star click writes straight into the form data (setting it just before post() sent the stale 0)
    const { data, setData, post, processing, errors } = useForm<ReviewForm>({
        product_id: product.id,
        rating: 0,
        comment: '',
        photos: [],
    });
    const rating = data.rating;

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('dashboard.title'), href: route('dashboard') },
        { title: t('orders.title'), href: route('orders.index') },
        { title: t('reviews.write_review'), href: '' },
    ];

    const photoErrors = Object.entries(errors as Record<string, string | undefined>)
        .filter(([key, message]) => message && (key === 'photos' || key.startsWith('photos.')))
        .map(([, message]) => message as string);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (rating === 0) return;

        post(route('reviews.store'), { forceFormData: true });
    };

    const formatCurrency = useMoney();

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('reviews.form.page_title', { product: product.name })} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <a href={route('orders.index')} aria-label={t('reviews.form.back')}>
                            <ArrowLeft className="h-4 w-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">{t('reviews.write_review')}</h1>
                        <p className="text-muted-foreground">{t('reviews.form.subtitle')}</p>
                    </div>
                </div>

                <div className="mx-auto max-w-2xl">
                    <Card>
                        <CardHeader>
                            <CardTitle>{t('reviews.form.product_info')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-4 rounded-lg border bg-muted/30 p-4">
                                <div className="flex-shrink-0">
                                    {product.image_url ? (
                                        <img src={product.image_url} alt={product.name} className="h-20 w-20 rounded-md object-cover" />
                                    ) : (
                                        <div className="flex h-20 w-20 items-center justify-center rounded-md bg-muted">
                                            <Package className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <h3 className="font-medium">{product.name}</h3>
                                    <p className="mt-1 text-sm text-muted-foreground">{formatCurrency(product.price)}</p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>{t('reviews.form.your_review')}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Star Rating */}
                                <div className="space-y-2">
                                    <Label id="rating-label">{t('reviews.form.rating_label')}</Label>
                                    <div className="flex gap-1" role="group" aria-labelledby="rating-label">
                                        {[1, 2, 3, 4, 5].map((star) => (
                                            <button
                                                key={star}
                                                type="button"
                                                className="p-1 transition-colors"
                                                onMouseEnter={() => setHoveredRating(star)}
                                                onMouseLeave={() => setHoveredRating(0)}
                                                onClick={() => setData('rating', star)}
                                                aria-label={t('reviews.form.rate_n', { n: star })}
                                                aria-pressed={rating === star}
                                            >
                                                <Star
                                                    className={`h-8 w-8 ${
                                                        star <= (hoveredRating || rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'
                                                    }`}
                                                />
                                            </button>
                                        ))}
                                    </div>
                                    {rating > 0 && <p className="text-sm text-muted-foreground">{t(`reviews.form.rating_${rating}`)}</p>}
                                    {errors.rating && <p className="text-sm text-red-600">{errors.rating}</p>}
                                </div>

                                {/* Comment */}
                                <div className="space-y-2">
                                    <Label htmlFor="comment">{t('reviews.form.comment_label')}</Label>
                                    <Textarea
                                        id="comment"
                                        placeholder={t('reviews.comment_placeholder')}
                                        value={data.comment}
                                        onChange={(e) => setData('comment', e.target.value)}
                                        rows={4}
                                        maxLength={1000}
                                        className="resize-none"
                                    />
                                    <p className="text-xs text-muted-foreground">{t('reviews.form.characters', { count: data.comment.length })}</p>
                                    {errors.comment && <p className="text-sm text-red-600">{errors.comment}</p>}
                                </div>

                                {/* Photos: stored pending, shown only after moderation */}
                                <ReviewPhotoInput
                                    value={data.photos}
                                    onChange={(files) => setData('photos', files)}
                                    limits={photoLimits}
                                    errors={photoErrors}
                                    disabled={processing}
                                />

                                {/* Submit Buttons */}
                                <div className="flex gap-3 pt-4">
                                    <Button type="submit" disabled={processing || rating === 0} className="flex-1">
                                        {processing ? t('reviews.submitting') : t('reviews.submit_review')}
                                    </Button>
                                    <Button type="button" variant="outline" asChild className="flex-1">
                                        <a href={route('orders.index')}>{t('reviews.form.cancel')}</a>
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
};

export default ReviewCreatePage;

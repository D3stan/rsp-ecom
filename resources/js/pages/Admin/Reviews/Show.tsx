import ReviewMediaModeration, { type AdminReviewMedia } from '@/components/reviews/admin/ReviewMediaModeration';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useToast } from '@/contexts/ToastContext';
import { useTranslation } from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Calendar, Check, ChevronLeft, ChevronRight, Package, Star, Trash2, User, X } from 'lucide-react';
import { useState } from 'react';

interface User {
    id: number;
    name: string;
    email: string;
}

interface Product {
    id: number;
    name: string;
    slug: string;
}

interface Review {
    id: number;
    user?: User;
    product: Product;
    rating: number;
    comment: string;
    is_approved: boolean;
    created_at: string;
    updated_at: string;
    media?: AdminReviewMedia[];
}

interface Props {
    review: Review;
    navigation: {
        next: { id: number } | null;
        previous: { id: number } | null;
    };
}

const statusColors = {
    approved: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
};

export default function ReviewShow({ review, navigation }: Props) {
    const { t, locale } = useTranslation();
    const [isUpdating, setIsUpdating] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const { addToast } = useToast();

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(locale, {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const renderStars = (rating: number) => {
        return (
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                    <Star key={star} className={`h-5 w-5 ${star <= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                ))}
                <span className="ml-2 text-lg font-medium">({rating}/5)</span>
            </div>
        );
    };

    const handleStatusUpdate = async (isApproved: boolean) => {
        setIsUpdating(true);
        try {
            await router.patch(
                `/admin/reviews/${review.id}`,
                {
                    is_approved: isApproved,
                },
                {
                    preserveState: true,
                    // success/failure is the controller flash, toasted by the layout
                    onFinish: () => {
                        setIsUpdating(false);
                    },
                },
            );
        } catch {
            addToast({
                type: 'error',
                title: t('admin.reviews.toast_update_failed'),
            });
            setIsUpdating(false);
        }
    };

    const handleDelete = async () => {
        try {
            // the controller redirects to the reviews index with the flash, toasted by the layout
            await router.delete(`/admin/reviews/${review.id}`);
        } catch {
            addToast({
                type: 'error',
                title: t('admin.reviews.toast_delete_failed'),
            });
        }
        setDeleteDialogOpen(false);
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.reviews.title'), href: '/admin/reviews' },
                { title: t('admin.reviews.review_details'), href: `/admin/reviews/${review.id}` },
            ]}
        >
            <Head title={t('admin.reviews.meta_show_title', { name: review.user?.name || t('admin.reviews.guest') })} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/reviews">
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                {t('admin.reviews.back_to_reviews')}
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('admin.reviews.review_details')}</h1>
                            <p className="text-sm text-muted-foreground md:text-base">
                                {t('admin.reviews.review_by_user', { name: review.user?.name || t('admin.reviews.guest_user') })}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Badge className={statusColors[review.is_approved ? 'approved' : 'pending']} variant="secondary">
                            {review.is_approved ? t('admin.reviews.status_approved') : t('admin.reviews.status_pending')}
                        </Badge>
                    </div>
                </div>

                {/* Review Content */}
                <div className="grid gap-6 md:grid-cols-3">
                    {/* Main Review Card */}
                    <Card className="p-6 md:col-span-2">
                        <div className="space-y-6">
                            {/* Rating */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">{t('admin.reviews.section_rating')}</h2>
                                {renderStars(review.rating)}
                            </div>

                            {/* Review Comment */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">{t('admin.reviews.section_review')}</h2>
                                <div className="rounded-lg bg-muted/50 p-4">
                                    <p className="text-sm leading-relaxed md:text-base">{review.comment || t('admin.reviews.no_comment')}</p>
                                </div>
                            </div>

                            <ReviewMediaModeration reviewId={review.id} reviewApproved={review.is_approved} media={review.media ?? []} />

                            {/* Product Information */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">{t('admin.reviews.section_product')}</h2>
                                <div className="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                                    <Package className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <Link href={`/admin/products/${review.product.id}`} className="font-medium text-primary hover:underline">
                                            {review.product.name}
                                        </Link>
                                        <p className="text-sm text-muted-foreground">{t('admin.reviews.product_id', { id: review.product.id })}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Customer Information */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">{t('admin.reviews.section_customer')}</h2>
                                <div className="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                                    <User className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{review.user?.name || t('admin.reviews.guest_user')}</p>
                                        <p className="text-sm text-muted-foreground">{review.user?.email || t('admin.reviews.no_email')}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Dates */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">{t('admin.reviews.section_timeline')}</h2>
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3 text-sm">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-muted-foreground">{t('admin.reviews.created_at')}</span>
                                        <span className="font-medium">{formatDate(review.created_at)}</span>
                                    </div>
                                    {review.updated_at !== review.created_at && (
                                        <div className="flex items-center gap-3 text-sm">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">{t('admin.reviews.last_updated')}</span>
                                            <span className="font-medium">{formatDate(review.updated_at)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </Card>

                    {/* Actions Sidebar */}
                    <Card className="h-fit p-6">
                        <h2 className="mb-4 text-lg font-semibold">{t('admin.reviews.section_actions')}</h2>
                        <div className="space-y-3">
                            {!review.is_approved ? (
                                <>
                                    <Button
                                        variant="default"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(true)}
                                        disabled={isUpdating}
                                        className="w-full bg-green-600 hover:bg-green-700"
                                    >
                                        <Check className="mr-2 h-4 w-4" />
                                        {t('admin.reviews.approve_review')}
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(false)}
                                        disabled={isUpdating}
                                        className="w-full"
                                    >
                                        <X className="mr-2 h-4 w-4" />
                                        {t('admin.reviews.reject_review')}
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleStatusUpdate(false)}
                                    disabled={isUpdating}
                                    className="w-full"
                                >
                                    <X className="mr-2 h-4 w-4" />
                                    {t('admin.reviews.unapprove_review')}
                                </Button>
                            )}

                            <div className="border-t pt-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setDeleteDialogOpen(true)}
                                    className="w-full text-red-600 hover:bg-red-50 hover:text-red-700"
                                >
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    {t('admin.reviews.action_delete_review')}
                                </Button>
                            </div>
                        </div>

                        {/* Quick Stats */}
                        <div className="mt-6 border-t pt-6">
                            <h3 className="mb-3 text-sm font-medium text-muted-foreground">{t('admin.reviews.quick_info')}</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('admin.reviews.review_id_label')}</span>
                                    <span className="font-medium">#{review.id}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('admin.reviews.status_label')}</span>
                                    <span className={`font-medium ${review.is_approved ? 'text-green-600' : 'text-yellow-600'}`}>
                                        {review.is_approved ? t('admin.reviews.status_approved') : t('admin.reviews.status_pending')}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">{t('admin.reviews.rating_label')}</span>
                                    <span className="font-medium">{t('admin.reviews.stars_rating', { rating: review.rating })}</span>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Mobile Bottom Actions */}
                <div className="fixed right-0 bottom-0 left-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 md:hidden">
                    <div className="safe-area-inset-bottom">
                        {/* Navigation Row */}
                        <div className="flex gap-2 border-b border-border/50 p-2">
                            <Button variant="outline" size="sm" asChild disabled={!navigation.previous} className="flex-1">
                                {navigation.previous ? (
                                    <Link href={`/admin/reviews/${navigation.previous.id}`}>
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                        {t('admin.reviews.prev')}
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                        {t('admin.reviews.prev')}
                                    </span>
                                )}
                            </Button>

                            <Button variant="outline" size="sm" asChild disabled={!navigation.next} className="flex-1">
                                {navigation.next ? (
                                    <Link href={`/admin/reviews/${navigation.next.id}`}>
                                        {t('admin.reviews.next')}
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </Link>
                                ) : (
                                    <span>
                                        {t('admin.reviews.next')}
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </span>
                                )}
                            </Button>
                        </div>

                        {/* Action Buttons Row */}
                        <div className="flex gap-2 p-3">
                            {!review.is_approved ? (
                                <>
                                    <Button
                                        size="sm"
                                        onClick={() => handleStatusUpdate(true)}
                                        disabled={isUpdating}
                                        className="flex-1 bg-green-600 hover:bg-green-700"
                                    >
                                        <Check className="mr-1 h-4 w-4" />
                                        {t('admin.reviews.action_approve')}
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(false)}
                                        disabled={isUpdating}
                                        className="flex-1"
                                    >
                                        <X className="mr-1 h-4 w-4" />
                                        {t('admin.reviews.action_reject')}
                                    </Button>
                                </>
                            ) : (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => handleStatusUpdate(false)}
                                    disabled={isUpdating}
                                    className="flex-1"
                                >
                                    <X className="mr-1 h-4 w-4" />
                                    {t('admin.reviews.unapprove')}
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Bottom Padding */}
            <div className="py-10"></div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('admin.reviews.dialog_delete_title')}</DialogTitle>
                        <DialogDescription>{t('admin.reviews.dialog_delete_description')}</DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                            {t('admin.reviews.dialog_cancel')}
                        </Button>
                        <Button variant="destructive" onClick={handleDelete}>
                            {t('admin.reviews.dialog_delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

import ReviewMediaModeration, { type AdminReviewMedia } from '@/components/reviews/admin/ReviewMediaModeration';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { useToast } from '@/contexts/ToastContext';
import { useTranslation } from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    CheckCircle,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Clock,
    Eye,
    Filter,
    Image as ImageIcon,
    MessageSquare,
    MoreHorizontal,
    Search,
    Star,
    Trash2,
    TrendingUp,
    X,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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

interface KPIs {
    total: number;
    approved: number;
    pending: number;
    this_week: number;
    average_rating: number;
    pending_photos: number;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    reviews?: {
        data?: Review[];
        links?: PaginationLink[];
        meta?: {
            total: number;
            per_page: number;
            current_page: number;
            last_page: number;
            from: number;
            to: number;
        };
    };
    kpis: KPIs;
    filters: {
        status?: string;
        rating?: string;
        search?: string;
        date_from?: string;
        date_to?: string;
    };
}

const statusColors = {
    approved: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
};

export default function ReviewsIndex({ reviews, kpis, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || 'pending');
    const [ratingFilter, setRatingFilter] = useState(filters.rating || '');
    const [isKPIsOpen, setIsKPIsOpen] = useState(false);
    const [selectedReviews, setSelectedReviews] = useState<number[]>([]);
    const [isUpdating, setIsUpdating] = useState<number | null>(null);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [reviewToDelete, setReviewToDelete] = useState<number | null>(null);
    const { addToast } = useToast();
    const { t, locale } = useTranslation();

    // Add safety checks for reviews data
    const reviewsData = reviews?.data || [];
    const reviewsMeta = reviews?.meta || { total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 };
    const reviewsLinks = reviews?.links || [];

    // Instant filtering - apply filters immediately on change
    useEffect(() => {
        // Pagination remounts the page: without this check the effect would reload it without `page` (back to page 1)
        if (search === (filters.search || '') && statusFilter === (filters.status || 'pending') && ratingFilter === (filters.rating || '')) {
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(
                '/admin/reviews',
                {
                    search: search || undefined,
                    status: statusFilter || undefined,
                    rating: ratingFilter || undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300);

        return () => clearTimeout(timeoutId);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- deps are the local filter state; filters.* is only compared to skip redundant reloads
    }, [search, statusFilter, ratingFilter]);

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('pending');
        setRatingFilter('');
        router.get('/admin/reviews', { status: 'pending' });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(locale, {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const renderStars = (rating: number) => {
        return (
            <div className="flex items-center gap-1">
                {[1, 2, 3, 4, 5].map((star) => (
                    <Star key={star} className={`h-4 w-4 ${star <= rating ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} />
                ))}
                <span className="ml-1 text-sm text-muted-foreground">({rating})</span>
            </div>
        );
    };

    const handleReviewStatusUpdate = async (reviewId: number, isApproved: boolean) => {
        setIsUpdating(reviewId);
        try {
            await router.patch(
                `/admin/reviews/${reviewId}`,
                {
                    is_approved: isApproved,
                },
                {
                    preserveState: false, // Allow page to refresh with updated data
                    preserveScroll: true,
                    // success/failure is the controller flash, toasted by the layout
                    onFinish: () => {
                        setIsUpdating(null);
                    },
                },
            );
        } catch {
            addToast({
                type: 'error',
                title: t('admin.reviews.toast_update_failed'),
            });
            setIsUpdating(null);
        }
    };

    const handleBulkAction = async (action: 'approve' | 'reject') => {
        if (selectedReviews.length === 0) {
            addToast({
                type: 'error',
                title: t('admin.reviews.toast_select_reviews'),
            });
            return;
        }

        try {
            await router.patch(
                '/admin/reviews/bulk-update',
                {
                    review_ids: selectedReviews,
                    action: action,
                },
                {
                    preserveState: false, // Allow page to refresh with updated data
                    preserveScroll: true,
                    // success/failure is the controller flash, toasted by the layout
                    onSuccess: () => {
                        setSelectedReviews([]);
                    },
                },
            );
        } catch {
            addToast({
                type: 'error',
                title: t('admin.reviews.toast_bulk_failed', { action }),
            });
        }
    };

    const handleDeleteReview = async (reviewId: number) => {
        try {
            await router.delete(`/admin/reviews/${reviewId}`, {
                preserveState: true,
                preserveScroll: true,
                // success/failure is the controller flash, toasted by the layout
            });
        } catch {
            addToast({
                type: 'error',
                title: t('admin.reviews.toast_delete_failed'),
            });
        }
        setDeleteDialogOpen(false);
        setReviewToDelete(null);
    };

    const toggleReviewSelection = (reviewId: number) => {
        setSelectedReviews((prev) => (prev.includes(reviewId) ? prev.filter((id) => id !== reviewId) : [...prev, reviewId]));
    };

    const toggleSelectAll = () => {
        if (selectedReviews.length === reviewsData.length) {
            setSelectedReviews([]);
        } else {
            setSelectedReviews(reviewsData.map((review) => review.id));
        }
    };

    // Helper functions for navigation
    const getNextReviewId = (currentId: number) => {
        const currentIndex = reviewsData.findIndex((review) => review.id === currentId);
        if (currentIndex !== -1 && currentIndex < reviewsData.length - 1) {
            return reviewsData[currentIndex + 1].id;
        }
        return null;
    };

    const getPreviousReviewId = (currentId: number) => {
        const currentIndex = reviewsData.findIndex((review) => review.id === currentId);
        if (currentIndex > 0) {
            return reviewsData[currentIndex - 1].id;
        }
        return null;
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.reviews.title'), href: '/admin/reviews' },
            ]}
        >
            <Head title={t('admin.reviews.meta_title')} />

            <div className="space-y-4 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('admin.reviews.heading')}</h1>
                        <p className="text-sm text-muted-foreground md:text-base">{t('admin.reviews.subheading')}</p>
                    </div>
                </div>

                {/* Collapsible KPI Cards */}
                <Collapsible open={isKPIsOpen} onOpenChange={setIsKPIsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" className="w-full justify-between" size="sm">
                            <span className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" />
                                {t('admin.reviews.review_stats')}
                            </span>
                            {isKPIsOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-4 space-y-4">
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-6">
                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <MessageSquare className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">{t('admin.reviews.kpi_total')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.total}</p>
                                    <p className="text-xs text-muted-foreground">{t('admin.reviews.kpi_all_reviews')}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">{t('admin.reviews.kpi_approved')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.approved}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {t('admin.reviews.kpi_percent_of_total', {
                                            percent: kpis.total > 0 ? Math.round((kpis.approved / kpis.total) * 100) : 0,
                                        })}
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Clock className="h-4 w-4 text-yellow-600" />
                                    <h3 className="text-xs font-medium">{t('admin.reviews.kpi_pending')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.pending}</p>
                                    <p className="text-xs text-muted-foreground">{t('admin.reviews.kpi_need_review')}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Star className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">{t('admin.reviews.kpi_avg_rating')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{(kpis.average_rating || 0).toFixed(1)}</p>
                                    <p className="text-xs text-muted-foreground">{t('admin.reviews.kpi_out_of_five')}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <TrendingUp className="h-4 w-4 text-indigo-600" />
                                    <h3 className="text-xs font-medium">{t('admin.reviews.kpi_this_week')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.this_week}</p>
                                    <p className="text-xs text-muted-foreground">{t('admin.reviews.kpi_new_reviews')}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <ImageIcon className="h-4 w-4 text-orange-600" />
                                    <h3 className="text-xs font-medium">{t('reviews.admin.kpi_pending_photos')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.pending_photos}</p>
                                    <p className="text-xs text-muted-foreground">{t('reviews.admin.kpi_pending_photos_hint')}</p>
                                </div>
                            </Card>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                {/* Mobile-First Filters */}
                <Card className="p-4">
                    <div className="space-y-3">
                        {/* Search Bar */}
                        <div className="relative">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                            <Input
                                placeholder={t('admin.reviews.search_placeholder')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10"
                            />
                        </div>

                        {/* Filter Dropdowns */}
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">
                                    {t('admin.reviews.filter_status_label')}
                                </label>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">{t('admin.reviews.filter_all_status')}</option>
                                    <option value="approved">{t('admin.reviews.filter_approved')}</option>
                                    <option value="pending">{t('admin.reviews.filter_pending')}</option>
                                    <option value="photos">{t('reviews.admin.filter_photos')}</option>
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">
                                    {t('admin.reviews.filter_rating_label')}
                                </label>
                                <select
                                    value={ratingFilter}
                                    onChange={(e) => setRatingFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">{t('admin.reviews.filter_all_ratings')}</option>
                                    <option value="5">{t('admin.reviews.filter_5_stars')}</option>
                                    <option value="4">{t('admin.reviews.filter_4_stars')}</option>
                                    <option value="3">{t('admin.reviews.filter_3_stars')}</option>
                                    <option value="2">{t('admin.reviews.filter_2_stars')}</option>
                                    <option value="1">{t('admin.reviews.filter_1_star')}</option>
                                </select>
                            </div>

                            <div className="flex items-end">
                                {(search || statusFilter !== 'pending' || ratingFilter) && (
                                    <Button onClick={clearFilters} variant="outline" size="sm" className="w-full">
                                        {t('admin.reviews.clear_filters')}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Bulk Actions */}
                {selectedReviews.length > 0 && (
                    <Card className="p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <span className="text-sm font-medium">
                                {selectedReviews.length === 1
                                    ? t('admin.reviews.selected_single')
                                    : t('admin.reviews.selected_multiple', { count: selectedReviews.length })}
                            </span>
                            <br></br>
                            <div className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="default"
                                    onClick={() => handleBulkAction('approve')}
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    <Check className="mr-1 h-4 w-4" />
                                    {t('admin.reviews.approve_all')}
                                </Button>
                                <Button size="sm" variant="destructive" onClick={() => handleBulkAction('reject')}>
                                    <X className="mr-1 h-4 w-4" />
                                    {t('admin.reviews.reject_all')}
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Mobile-First Reviews Table */}
                <Card>
                    {/* Desktop Table View */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-4 text-left font-medium">
                                        <Checkbox
                                            checked={selectedReviews.length === reviewsData.length && reviewsData.length > 0}
                                            onCheckedChange={toggleSelectAll}
                                        />
                                    </th>
                                    <th className="p-4 text-left font-medium">{t('admin.reviews.th_review')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.reviews.th_product')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.reviews.th_customer')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.reviews.th_rating')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.reviews.th_status')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.reviews.th_date')}</th>
                                    <th className="p-4 text-right font-medium">{t('admin.reviews.th_actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reviewsData.length > 0 ? (
                                    reviewsData.map((review) => (
                                        <tr key={review.id} className="border-b hover:bg-muted/50">
                                            <td className="p-4">
                                                <Checkbox
                                                    checked={selectedReviews.includes(review.id)}
                                                    onCheckedChange={() => toggleReviewSelection(review.id)}
                                                />
                                            </td>
                                            <td className="p-4">
                                                <div className="max-w-xs space-y-2">
                                                    <p className="line-clamp-2 text-sm">{review.comment || t('admin.reviews.no_comment')}</p>
                                                    <ReviewMediaModeration
                                                        reviewId={review.id}
                                                        reviewApproved={review.is_approved}
                                                        media={review.media ?? []}
                                                        compact
                                                    />
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <Link
                                                    href={`/admin/products/${review.product.id}`}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {review.product.name}
                                                </Link>
                                            </td>
                                            <td className="p-4">
                                                <div>
                                                    <p className="font-medium">{review.user?.name || t('admin.reviews.guest_user')}</p>
                                                    <p className="text-sm text-muted-foreground">
                                                        {review.user?.email || t('admin.reviews.not_available')}
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="p-4">{renderStars(review.rating)}</td>
                                            <td className="p-4">
                                                <div className="flex items-center gap-2">
                                                    <Badge className={statusColors[review.is_approved ? 'approved' : 'pending']} variant="secondary">
                                                        {review.is_approved ? t('admin.reviews.status_approved') : t('admin.reviews.status_pending')}
                                                    </Badge>
                                                    {!review.is_approved && (
                                                        <div className="flex gap-1">
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => handleReviewStatusUpdate(review.id, true)}
                                                                disabled={isUpdating === review.id}
                                                                className="h-6 w-6 p-0 text-green-600 hover:bg-green-50 hover:text-green-700"
                                                            >
                                                                <Check className="h-3 w-3" />
                                                            </Button>
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() => handleReviewStatusUpdate(review.id, false)}
                                                                disabled={isUpdating === review.id}
                                                                className="h-6 w-6 p-0 text-red-600 hover:bg-red-50 hover:text-red-700"
                                                            >
                                                                <X className="h-3 w-3" />
                                                            </Button>
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <span className="text-sm">{formatDate(review.created_at)}</span>
                                            </td>
                                            <td className="p-4 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/reviews/${review.id}`}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                {t('admin.reviews.action_view_details')}
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        {getPreviousReviewId(review.id) && (
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/admin/reviews/${getPreviousReviewId(review.id)}`}>
                                                                    <ChevronLeft className="mr-2 h-4 w-4" />
                                                                    {t('admin.reviews.action_previous_review')}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        )}
                                                        {getNextReviewId(review.id) && (
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/admin/reviews/${getNextReviewId(review.id)}`}>
                                                                    <ChevronRight className="mr-2 h-4 w-4" />
                                                                    {t('admin.reviews.action_next_review')}
                                                                </Link>
                                                            </DropdownMenuItem>
                                                        )}
                                                        {(getPreviousReviewId(review.id) || getNextReviewId(review.id)) && <DropdownMenuSeparator />}
                                                        <DropdownMenuItem
                                                            onClick={() => {
                                                                setReviewToDelete(review.id);
                                                                setDeleteDialogOpen(true);
                                                            }}
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            {t('admin.reviews.action_delete_review')}
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={8} className="py-8 text-center text-muted-foreground">
                                            {t('admin.reviews.no_reviews')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card View */}
                    <div className="space-y-3 p-3 md:hidden">
                        {reviewsData.length > 0 ? (
                            reviewsData.map((review) => (
                                <Card key={review.id} className="p-4">
                                    <div className="space-y-3">
                                        {/* Header */}
                                        <div className="flex items-start justify-between">
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    checked={selectedReviews.includes(review.id)}
                                                    onCheckedChange={() => toggleReviewSelection(review.id)}
                                                />
                                                <Badge className={statusColors[review.is_approved ? 'approved' : 'pending']} variant="secondary">
                                                    {review.is_approved ? t('admin.reviews.status_approved') : t('admin.reviews.status_pending')}
                                                </Badge>
                                            </div>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/admin/reviews/${review.id}`}>
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            {t('admin.reviews.action_view_details')}
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    {getPreviousReviewId(review.id) && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/reviews/${getPreviousReviewId(review.id)}`}>
                                                                <ChevronLeft className="mr-2 h-4 w-4" />
                                                                {t('admin.reviews.action_previous_review')}
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {getNextReviewId(review.id) && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/reviews/${getNextReviewId(review.id)}`}>
                                                                <ChevronRight className="mr-2 h-4 w-4" />
                                                                {t('admin.reviews.action_next_review')}
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {(getPreviousReviewId(review.id) || getNextReviewId(review.id)) && <DropdownMenuSeparator />}
                                                    <DropdownMenuItem
                                                        onClick={() => {
                                                            setReviewToDelete(review.id);
                                                            setDeleteDialogOpen(true);
                                                        }}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        {t('admin.reviews.action_delete_review')}
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>

                                        {/* Rating */}
                                        <div>{renderStars(review.rating)}</div>

                                        {/* Review Content */}
                                        <div className="space-y-2">
                                            <p className="text-sm">{review.comment || t('admin.reviews.no_comment')}</p>
                                            <ReviewMediaModeration
                                                reviewId={review.id}
                                                reviewApproved={review.is_approved}
                                                media={review.media ?? []}
                                                compact
                                            />
                                        </div>

                                        {/* Product & Customer Info */}
                                        <div className="space-y-2 text-sm">
                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground">{t('admin.reviews.label_product')}</span>
                                                <Link
                                                    href={`/admin/products/${review.product.id}`}
                                                    className="font-medium text-primary hover:underline"
                                                >
                                                    {review.product.name}
                                                </Link>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground">{t('admin.reviews.label_customer')}</span>
                                                <div className="text-right">
                                                    <p className="font-medium">{review.user?.name || t('admin.reviews.guest_user')}</p>
                                                    {review.user?.email && <p className="text-xs text-muted-foreground">{review.user.email}</p>}
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-muted-foreground">{t('admin.reviews.label_date')}</span>
                                                <span>{formatDate(review.created_at)}</span>
                                            </div>
                                        </div>

                                        {/* Action Buttons */}
                                        {!review.is_approved && (
                                            <div className="flex gap-2 pt-2">
                                                <Button
                                                    size="sm"
                                                    variant="default"
                                                    onClick={() => handleReviewStatusUpdate(review.id, true)}
                                                    disabled={isUpdating === review.id}
                                                    className="flex-1 bg-green-600 hover:bg-green-700"
                                                >
                                                    <Check className="mr-1 h-4 w-4" />
                                                    {t('admin.reviews.action_approve')}
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => handleReviewStatusUpdate(review.id, false)}
                                                    disabled={isUpdating === review.id}
                                                    className="flex-1"
                                                >
                                                    <X className="mr-1 h-4 w-4" />
                                                    {t('admin.reviews.action_reject')}
                                                </Button>
                                            </div>
                                        )}
                                    </div>
                                </Card>
                            ))
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">{t('admin.reviews.no_reviews')}</div>
                        )}
                    </div>

                    {/* Mobile-First Pagination */}
                    {reviewsMeta.last_page > 1 && (
                        <div className="border-t px-4 py-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-center text-sm text-muted-foreground md:text-left">
                                    {t('admin.reviews.showing_results', { from: reviewsMeta.from, to: reviewsMeta.to, total: reviewsMeta.total })}
                                </p>
                                <div className="flex items-center justify-center space-x-1 md:space-x-2">
                                    {reviewsLinks.map((link, index) => {
                                        if (!link.label) return null;
                                        return (
                                            <Button
                                                key={index}
                                                variant={link.active ? 'default' : 'outline'}
                                                size="sm"
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(
                                                            link.url,
                                                            {},
                                                            {
                                                                preserveState: true,
                                                                preserveScroll: false,
                                                            },
                                                        );
                                                    }
                                                }}
                                                disabled={!link.url}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                                className="h-8 min-w-[32px]"
                                            />
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    )}
                </Card>

                <div className="py-5"></div>

                {/* Mobile Bottom Navigation */}
                <div className="fixed right-0 bottom-0 left-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 md:hidden">
                    <div className="safe-area-inset-bottom">
                        <div className="flex justify-center gap-2 p-3">
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (reviewsMeta.current_page > 1) {
                                        router.get(
                                            '/admin/reviews',
                                            {
                                                ...filters,
                                                page: reviewsMeta.current_page - 1,
                                            },
                                            {
                                                preserveState: true,
                                                preserveScroll: false,
                                            },
                                        );
                                    }
                                }}
                                disabled={reviewsMeta.current_page <= 1}
                                className="h-12 flex-1 text-xs"
                            >
                                <ChevronLeft className="mr-1 h-4 w-4" />
                                {t('admin.reviews.prev_page')}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                                className="h-12 flex-1 text-xs"
                            >
                                <Filter className="mr-1 h-4 w-4" />
                                {t('admin.reviews.filters_button')}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    if (reviewsMeta.current_page < reviewsMeta.last_page) {
                                        router.get(
                                            '/admin/reviews',
                                            {
                                                ...filters,
                                                page: reviewsMeta.current_page + 1,
                                            },
                                            {
                                                preserveState: true,
                                                preserveScroll: false,
                                            },
                                        );
                                    }
                                }}
                                disabled={reviewsMeta.current_page >= reviewsMeta.last_page}
                                className="h-12 flex-1 text-xs"
                            >
                                {t('admin.reviews.next_page')}
                                <ChevronRight className="ml-1 h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                </div>
            </div>

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
                        <Button variant="destructive" onClick={() => reviewToDelete && handleDeleteReview(reviewToDelete)}>
                            {t('admin.reviews.dialog_delete')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Search, 
    Filter, 
    Eye, 
    Trash2,
    MoreHorizontal,
    Star,
    StarOff,
    Users,
    MessageSquare,
    ChevronDown,
    ChevronUp,
    CheckCircle,
    XCircle,
    Clock,
    TrendingUp,
    Check,
    X,
    ChevronLeft,
    ChevronRight
} from 'lucide-react';
import { useState, useEffect } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Checkbox } from '@/components/ui/checkbox';
import { useToast } from '@/contexts/ToastContext';

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
}

interface KPIs {
    total: number;
    approved: number;
    pending: number;
    this_week: number;
    average_rating: number;
}

interface Props {
    reviews?: {
        data?: Review[];
        links?: any[];
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

    // Add safety checks for reviews data
    const reviewsData = reviews?.data || [];
    const reviewsMeta = reviews?.meta || { total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 };
    const reviewsLinks = reviews?.links || [];

    // Instant filtering - apply filters immediately on change
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get('/admin/reviews', {
                search: search || undefined,
                status: statusFilter || undefined,
                rating: ratingFilter || undefined,
            }, { 
                preserveState: true,
                preserveScroll: true,
                replace: true
            });
        }, 300);

        return () => clearTimeout(timeoutId);
    }, [search, statusFilter, ratingFilter]);

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('pending');
        setRatingFilter('');
        router.get('/admin/reviews', { status: 'pending' });
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('it-IT', {
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
                    <Star
                        key={star}
                        className={`h-4 w-4 ${
                            star <= rating
                                ? 'fill-yellow-400 text-yellow-400'
                                : 'text-gray-300'
                        }`}
                    />
                ))}
                <span className="ml-1 text-sm text-muted-foreground">({rating})</span>
            </div>
        );
    };

    const handleReviewStatusUpdate = async (reviewId: number, isApproved: boolean) => {
        setIsUpdating(reviewId);
        try {
            await router.patch(`/admin/reviews/${reviewId}`, {
                is_approved: isApproved,
            }, {
                preserveState: false, // Allow page to refresh with updated data
                preserveScroll: true,
                onSuccess: () => {
                    addToast({
                        type: 'success',
                        title: `Review ${isApproved ? 'approved' : 'rejected'} successfully`,
                    });
                },
                onError: () => {
                    addToast({
                        type: 'error',
                        title: 'Failed to update review status',
                    });
                },
                onFinish: () => {
                    setIsUpdating(null);
                }
            });
        } catch (error) {
            addToast({
                type: 'error',
                title: 'Failed to update review status',
            });
            setIsUpdating(null);
        }
    };

    const handleBulkAction = async (action: 'approve' | 'reject') => {
        if (selectedReviews.length === 0) {
            addToast({
                type: 'error',
                title: 'Please select reviews to update',
            });
            return;
        }

        try {
            await router.patch('/admin/reviews/bulk-update', {
                review_ids: selectedReviews,
                action: action,
            }, {
                preserveState: false, // Allow page to refresh with updated data
                preserveScroll: true,
                onSuccess: () => {
                    addToast({
                        type: 'success',
                        title: `${selectedReviews.length} reviews ${action}d successfully`,
                    });
                    setSelectedReviews([]);
                },
                onError: () => {
                    addToast({
                        type: 'error',
                        title: `Failed to ${action} reviews`,
                    });
                }
            });
        } catch (error) {
            addToast({
                type: 'error',
                title: `Failed to ${action} reviews`,
            });
        }
    };

    const handleDeleteReview = async (reviewId: number) => {
        try {
            await router.delete(`/admin/reviews/${reviewId}`, {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    addToast({
                        type: 'success',
                        title: 'Review deleted successfully',
                    });
                },
                onError: () => {
                    addToast({
                        type: 'error',
                        title: 'Failed to delete review',
                    });
                }
            });
        } catch (error) {
            addToast({
                type: 'error',
                title: 'Failed to delete review',
            });
        }
        setDeleteDialogOpen(false);
        setReviewToDelete(null);
    };

    const toggleReviewSelection = (reviewId: number) => {
        setSelectedReviews(prev => 
            prev.includes(reviewId) 
                ? prev.filter(id => id !== reviewId)
                : [...prev, reviewId]
        );
    };

    const toggleSelectAll = () => {
        if (selectedReviews.length === reviewsData.length) {
            setSelectedReviews([]);
        } else {
            setSelectedReviews(reviewsData.map(review => review.id));
        }
    };

    // Helper functions for navigation
    const getNextReviewId = (currentId: number) => {
        const currentIndex = reviewsData.findIndex(review => review.id === currentId);
        if (currentIndex !== -1 && currentIndex < reviewsData.length - 1) {
            return reviewsData[currentIndex + 1].id;
        }
        return null;
    };

    const getPreviousReviewId = (currentId: number) => {
        const currentIndex = reviewsData.findIndex(review => review.id === currentId);
        if (currentIndex > 0) {
            return reviewsData[currentIndex - 1].id;
        }
        return null;
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Reviews', href: '/admin/reviews' },
            ]}
        >
            <Head title="Reviews Moderation - Admin" />

            <div className="space-y-4 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Reviews Moderation</h1>
                        <p className="text-sm md:text-base text-muted-foreground">
                            Approve, reject or manage customer reviews
                        </p>
                    </div>
                </div>

                {/* Collapsible KPI Cards */}
                <Collapsible open={isKPIsOpen} onOpenChange={setIsKPIsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button 
                            variant="outline" 
                            className="w-full justify-between"
                            size="sm"
                        >
                            <span className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" />
                                Review Stats
                            </span>
                            {isKPIsOpen ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-4 mt-4">
                        <div className="grid gap-3 grid-cols-2 lg:grid-cols-5">
                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <MessageSquare className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">Total</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.total}</p>
                                    <p className="text-xs text-muted-foreground">All reviews</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <CheckCircle className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">Approved</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.approved}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {kpis.total > 0 ? Math.round((kpis.approved / kpis.total) * 100) : 0}% of total
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Clock className="h-4 w-4 text-yellow-600" />
                                    <h3 className="text-xs font-medium">Pending</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.pending}</p>
                                    <p className="text-xs text-muted-foreground">Need review</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Star className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">Avg Rating</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{(kpis.average_rating || 0).toFixed(1)}</p>
                                    <p className="text-xs text-muted-foreground">Out of 5.0</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <TrendingUp className="h-4 w-4 text-indigo-600" />
                                    <h3 className="text-xs font-medium">This Week</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.this_week}</p>
                                    <p className="text-xs text-muted-foreground">New reviews</p>
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
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search reviews, products, customers..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10"
                            />
                        </div>
                        
                        {/* Filter Dropdowns */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">
                                    Status
                                </label>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="w-full px-3 py-2 border border-input bg-background rounded-md text-sm"
                                >
                                    <option value="">All Status</option>
                                    <option value="approved">Approved</option>
                                    <option value="pending">Pending</option>
                                </select>
                            </div>
                            
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">
                                    Rating
                                </label>
                                <select
                                    value={ratingFilter}
                                    onChange={(e) => setRatingFilter(e.target.value)}
                                    className="w-full px-3 py-2 border border-input bg-background rounded-md text-sm"
                                >
                                    <option value="">All Ratings</option>
                                    <option value="5">5 Stars</option>
                                    <option value="4">4 Stars</option>
                                    <option value="3">3 Stars</option>
                                    <option value="2">2 Stars</option>
                                    <option value="1">1 Star</option>
                                </select>
                            </div>
                            
                            <div className="flex items-end">
                                {(search || statusFilter !== 'pending' || ratingFilter) && (
                                    <Button 
                                        onClick={clearFilters} 
                                        variant="outline" 
                                        size="sm"
                                        className="w-full"
                                    >
                                        Clear Filters
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
                                {selectedReviews.length} review{selectedReviews.length > 1 ? 's' : ''} selected
                            </span>
                            <br></br>
                            <div className="flex gap-2">
                                <Button 
                                    size="sm" 
                                    variant="default"
                                    onClick={() => handleBulkAction('approve')}
                                    className="bg-green-600 hover:bg-green-700"
                                >
                                    <Check className="h-4 w-4 mr-1" />
                                    Approve All
                                </Button>
                                <Button 
                                    size="sm" 
                                    variant="destructive"
                                    onClick={() => handleBulkAction('reject')}
                                >
                                    <X className="h-4 w-4 mr-1" />
                                    Reject All
                                </Button>
                            </div>
                        </div>
                    </Card>
                )}

                {/* Mobile-First Reviews Table */}
                <Card>
                    {/* Desktop Table View */}
                    <div className="hidden md:block overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left p-4 font-medium">
                                        <Checkbox
                                            checked={selectedReviews.length === reviewsData.length && reviewsData.length > 0}
                                            onCheckedChange={toggleSelectAll}
                                        />
                                    </th>
                                    <th className="text-left p-4 font-medium">Review</th>
                                    <th className="text-left p-4 font-medium">Product</th>
                                    <th className="text-left p-4 font-medium">Customer</th>
                                    <th className="text-left p-4 font-medium">Rating</th>
                                    <th className="text-left p-4 font-medium">Status</th>
                                    <th className="text-left p-4 font-medium">Date</th>
                                    <th className="text-right p-4 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {reviewsData.length > 0 ? reviewsData.map((review) => (
                                    <tr key={review.id} className="border-b hover:bg-muted/50">
                                        <td className="p-4">
                                            <Checkbox
                                                checked={selectedReviews.includes(review.id)}
                                                onCheckedChange={() => toggleReviewSelection(review.id)}
                                            />
                                        </td>
                                        <td className="p-4">
                                            <div className="max-w-xs">
                                                <p className="text-sm line-clamp-2">
                                                    {review.comment || 'No comment provided'}
                                                </p>
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
                                                <p className="font-medium">
                                                    {review.user?.name || 'Guest User'}
                                                </p>
                                                <p className="text-sm text-muted-foreground">
                                                    {review.user?.email || 'N/A'}
                                                </p>
                                            </div>
                                        </td>
                                        <td className="p-4">
                                            {renderStars(review.rating)}
                                        </td>
                                        <td className="p-4">
                                            <div className="flex items-center gap-2">
                                                <Badge
                                                    className={statusColors[review.is_approved ? 'approved' : 'pending']}
                                                    variant="secondary"
                                                >
                                                    {review.is_approved ? 'Approved' : 'Pending'}
                                                </Badge>
                                                {!review.is_approved && (
                                                    <div className="flex gap-1">
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleReviewStatusUpdate(review.id, true)}
                                                            disabled={isUpdating === review.id}
                                                            className="h-6 w-6 p-0 text-green-600 hover:text-green-700 hover:bg-green-50"
                                                        >
                                                            <Check className="h-3 w-3" />
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() => handleReviewStatusUpdate(review.id, false)}
                                                            disabled={isUpdating === review.id}
                                                            className="h-6 w-6 p-0 text-red-600 hover:text-red-700 hover:bg-red-50"
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
                                                            <Eye className="h-4 w-4 mr-2" />
                                                            View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    {getPreviousReviewId(review.id) && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/reviews/${getPreviousReviewId(review.id)}`}>
                                                                <ChevronLeft className="h-4 w-4 mr-2" />
                                                                Previous Review
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {getNextReviewId(review.id) && (
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/reviews/${getNextReviewId(review.id)}`}>
                                                                <ChevronRight className="h-4 w-4 mr-2" />
                                                                Next Review
                                                            </Link>
                                                        </DropdownMenuItem>
                                                    )}
                                                    {(getPreviousReviewId(review.id) || getNextReviewId(review.id)) && (
                                                        <DropdownMenuSeparator />
                                                    )}
                                                    <DropdownMenuItem 
                                                        onClick={() => {
                                                            setReviewToDelete(review.id);
                                                            setDeleteDialogOpen(true);
                                                        }}
                                                    >
                                                        <Trash2 className="h-4 w-4 mr-2" />
                                                        Delete Review
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </td>
                                    </tr>
                                )) : (
                                    <tr>
                                        <td colSpan={8} className="text-center py-8 text-muted-foreground">
                                            No reviews found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card View */}
                    <div className="md:hidden space-y-3 p-3">
                        {reviewsData.length > 0 ? reviewsData.map((review) => (
                            <Card key={review.id} className="p-4">
                                <div className="space-y-3">
                                    {/* Header */}
                                    <div className="flex items-start justify-between">
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                checked={selectedReviews.includes(review.id)}
                                                onCheckedChange={() => toggleReviewSelection(review.id)}
                                            />
                                            <Badge
                                                className={statusColors[review.is_approved ? 'approved' : 'pending']}
                                                variant="secondary"
                                            >
                                                {review.is_approved ? 'Approved' : 'Pending'}
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
                                                        <Eye className="h-4 w-4 mr-2" />
                                                        View Details
                                                    </Link>
                                                </DropdownMenuItem>
                                                <DropdownMenuSeparator />
                                                {getPreviousReviewId(review.id) && (
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/admin/reviews/${getPreviousReviewId(review.id)}`}>
                                                            <ChevronLeft className="h-4 w-4 mr-2" />
                                                            Previous Review
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                {getNextReviewId(review.id) && (
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/admin/reviews/${getNextReviewId(review.id)}`}>
                                                            <ChevronRight className="h-4 w-4 mr-2" />
                                                            Next Review
                                                        </Link>
                                                    </DropdownMenuItem>
                                                )}
                                                {(getPreviousReviewId(review.id) || getNextReviewId(review.id)) && (
                                                    <DropdownMenuSeparator />
                                                )}
                                                <DropdownMenuItem 
                                                    onClick={() => {
                                                        setReviewToDelete(review.id);
                                                        setDeleteDialogOpen(true);
                                                    }}
                                                >
                                                    <Trash2 className="h-4 w-4 mr-2" />
                                                    Delete Review
                                                </DropdownMenuItem>
                                            </DropdownMenuContent>
                                        </DropdownMenu>
                                    </div>

                                    {/* Rating */}
                                    <div>
                                        {renderStars(review.rating)}
                                    </div>

                                    {/* Review Content */}
                                    <div>
                                        <p className="text-sm">
                                            {review.comment || 'No comment provided'}
                                        </p>
                                    </div>

                                    {/* Product & Customer Info */}
                                    <div className="space-y-2 text-sm">
                                        <div className="flex justify-between items-center">
                                            <span className="text-muted-foreground">Product:</span>
                                            <Link
                                                href={`/admin/products/${review.product.id}`}
                                                className="font-medium text-primary hover:underline"
                                            >
                                                {review.product.name}
                                            </Link>
                                        </div>
                                        
                                        <div className="flex justify-between items-center">
                                            <span className="text-muted-foreground">Customer:</span>
                                            <div className="text-right">
                                                <p className="font-medium">{review.user?.name || 'Guest User'}</p>
                                                {review.user?.email && (
                                                    <p className="text-xs text-muted-foreground">{review.user.email}</p>
                                                )}
                                            </div>
                                        </div>
                                        
                                        <div className="flex justify-between items-center">
                                            <span className="text-muted-foreground">Date:</span>
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
                                                <Check className="h-4 w-4 mr-1" />
                                                Approve
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="destructive"
                                                onClick={() => handleReviewStatusUpdate(review.id, false)}
                                                disabled={isUpdating === review.id}
                                                className="flex-1"
                                            >
                                                <X className="h-4 w-4 mr-1" />
                                                Reject
                                            </Button>
                                        </div>
                                    )}
                                </div>
                            </Card>
                        )) : (
                            <div className="text-center py-8 text-muted-foreground">
                                No reviews found
                            </div>
                        )}
                    </div>

                    {/* Mobile-First Pagination */}
                    {reviewsMeta.last_page > 1 && (
                        <div className="px-4 py-4 border-t">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-sm text-muted-foreground text-center md:text-left">
                                    Showing {reviewsMeta.from} to {reviewsMeta.to} of {reviewsMeta.total} results
                                </p>
                                <div className="flex items-center justify-center space-x-1 md:space-x-2">
                                    {reviewsLinks.map((link, index) => {
                                        if (!link.label) return null;
                                        return (
                                            <Button
                                                key={index}
                                                variant={link.active ? "default" : "outline"}
                                                size="sm"
                                                onClick={() => {
                                                    if (link.url) {
                                                        router.get(link.url, {}, { 
                                                            preserveState: true,
                                                            preserveScroll: false
                                                        });
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
                
                                <div className='py-5'></div>
                
                {/* Mobile Bottom Navigation */}
                <div className="md:hidden fixed bottom-0 left-0 right-0 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-t z-50">
                    <div className="safe-area-inset-bottom">
                        <div className="flex justify-center gap-2 p-3">
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => {
                                    if (reviewsMeta.current_page > 1) {
                                        router.get('/admin/reviews', {
                                            ...filters,
                                            page: reviewsMeta.current_page - 1
                                        }, { 
                                            preserveState: true,
                                            preserveScroll: false
                                        });
                                    }
                                }}
                                disabled={reviewsMeta.current_page <= 1}
                                className="flex-1 h-12 text-xs"
                            >
                                <ChevronLeft className="h-4 w-4 mr-1" />
                                Prev Page
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                                className="flex-1 h-12 text-xs"
                            >
                                <Filter className="h-4 w-4 mr-1" />
                                Filters
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => {
                                    if (reviewsMeta.current_page < reviewsMeta.last_page) {
                                        router.get('/admin/reviews', {
                                            ...filters,
                                            page: reviewsMeta.current_page + 1
                                        }, { 
                                            preserveState: true,
                                            preserveScroll: false
                                        });
                                    }
                                }}
                                disabled={reviewsMeta.current_page >= reviewsMeta.last_page}
                                className="flex-1 h-12 text-xs"
                            >
                                Next Page
                                <ChevronRight className="h-4 w-4 ml-1" />
                            </Button>
                        </div>
                    </div>
                </div>
                
            </div>

            {/* Delete Confirmation Dialog */}
            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Review</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this review? This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteDialogOpen(false)}>
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={() => reviewToDelete && handleDeleteReview(reviewToDelete)}
                        >
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

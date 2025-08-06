import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Head, Link, router } from '@inertiajs/react';
import { 
    ArrowLeft, 
    Star,
    User,
    Package,
    Calendar,
    MessageSquare,
    Check,
    X,
    Edit,
    Trash2,
    ChevronLeft,
    ChevronRight
} from 'lucide-react';
import { useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
    const [isUpdating, setIsUpdating] = useState(false);
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const { addToast } = useToast();

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('it-IT', {
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
                    <Star
                        key={star}
                        className={`h-5 w-5 ${
                            star <= rating
                                ? 'fill-yellow-400 text-yellow-400'
                                : 'text-gray-300'
                        }`}
                    />
                ))}
                <span className="ml-2 text-lg font-medium">({rating}/5)</span>
            </div>
        );
    };

    const handleStatusUpdate = async (isApproved: boolean) => {
        setIsUpdating(true);
        try {
            await router.patch(`/admin/reviews/${review.id}`, {
                is_approved: isApproved,
            }, {
                preserveState: true,
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
                    setIsUpdating(false);
                }
            });
        } catch (error) {
            addToast({
                type: 'error',
                title: 'Failed to update review status',
            });
            setIsUpdating(false);
        }
    };

    const handleDelete = async () => {
        try {
            await router.delete(`/admin/reviews/${review.id}`, {
                onSuccess: () => {
                    addToast({
                        type: 'success',
                        title: 'Review deleted successfully',
                    });
                    router.visit('/admin/reviews');
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
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Reviews', href: '/admin/reviews' },
                { title: 'Review Details', href: `/admin/reviews/${review.id}` },
            ]}
        >
            <Head title={`Review by ${review.user?.name || 'Guest'} - Admin`} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div className="flex items-center gap-3">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/reviews">
                                <ArrowLeft className="h-4 w-4 mr-1" />
                                Back to Reviews
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Review Details</h1>
                            <p className="text-sm md:text-base text-muted-foreground">
                                Review by {review.user?.name || 'Guest User'}
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Badge
                            className={statusColors[review.is_approved ? 'approved' : 'pending']}
                            variant="secondary"
                        >
                            {review.is_approved ? 'Approved' : 'Pending'}
                        </Badge>
                    </div>
                </div>

                {/* Review Content */}
                <div className="grid gap-6 md:grid-cols-3">
                    {/* Main Review Card */}
                    <Card className="md:col-span-2 p-6">
                        <div className="space-y-6">
                            {/* Rating */}
                            <div>
                                <h2 className="text-lg font-semibold mb-3">Rating</h2>
                                {renderStars(review.rating)}
                            </div>

                            {/* Review Comment */}
                            <div>
                                <h2 className="text-lg font-semibold mb-3">Review</h2>
                                <div className="bg-muted/50 p-4 rounded-lg">
                                    <p className="text-sm md:text-base leading-relaxed">
                                        {review.comment || 'No comment provided'}
                                    </p>
                                </div>
                            </div>

                            {/* Product Information */}
                            <div>
                                <h2 className="text-lg font-semibold mb-3">Product</h2>
                                <div className="flex items-center gap-3 p-3 bg-muted/30 rounded-lg">
                                    <Package className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <Link
                                            href={`/admin/products/${review.product.id}`}
                                            className="font-medium text-primary hover:underline"
                                        >
                                            {review.product.name}
                                        </Link>
                                        <p className="text-sm text-muted-foreground">
                                            Product ID: {review.product.id}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Customer Information */}
                            <div>
                                <h2 className="text-lg font-semibold mb-3">Customer</h2>
                                <div className="flex items-center gap-3 p-3 bg-muted/30 rounded-lg">
                                    <User className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{review.user?.name || 'Guest User'}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {review.user?.email || 'No email provided'}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            {/* Dates */}
                            <div>
                                <h2 className="text-lg font-semibold mb-3">Timeline</h2>
                                <div className="space-y-3">
                                    <div className="flex items-center gap-3 text-sm">
                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-muted-foreground">Created:</span>
                                        <span className="font-medium">{formatDate(review.created_at)}</span>
                                    </div>
                                    {review.updated_at !== review.created_at && (
                                        <div className="flex items-center gap-3 text-sm">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-muted-foreground">Last Updated:</span>
                                            <span className="font-medium">{formatDate(review.updated_at)}</span>
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>
                    </Card>

                    {/* Actions Sidebar */}
                    <Card className="p-6 h-fit">
                        <h2 className="text-lg font-semibold mb-4">Actions</h2>
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
                                        <Check className="h-4 w-4 mr-2" />
                                        Approve Review
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(false)}
                                        disabled={isUpdating}
                                        className="w-full"
                                    >
                                        <X className="h-4 w-4 mr-2" />
                                        Reject Review
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
                                    <X className="h-4 w-4 mr-2" />
                                    Unapprove Review
                                </Button>
                            )}
                            
                            <div className="border-t pt-3">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setDeleteDialogOpen(true)}
                                    className="w-full text-red-600 hover:text-red-700 hover:bg-red-50"
                                >
                                    <Trash2 className="h-4 w-4 mr-2" />
                                    Delete Review
                                </Button>
                            </div>
                        </div>

                        {/* Quick Stats */}
                        <div className="mt-6 pt-6 border-t">
                            <h3 className="text-sm font-medium text-muted-foreground mb-3">Quick Info</h3>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Review ID:</span>
                                    <span className="font-medium">#{review.id}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Status:</span>
                                    <span className={`font-medium ${review.is_approved ? 'text-green-600' : 'text-yellow-600'}`}>
                                        {review.is_approved ? 'Approved' : 'Pending'}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Rating:</span>
                                    <span className="font-medium">{review.rating}/5 stars</span>
                                </div>
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Mobile Bottom Actions */}
                <div className="md:hidden fixed bottom-0 left-0 right-0 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-t z-50">
                    <div className="safe-area-inset-bottom">
                        {/* Navigation Row */}
                        <div className="flex gap-2 p-2 border-b border-border/50">
                            <Button 
                                variant="outline" 
                                size="sm" 
                                asChild
                                disabled={!navigation.previous}
                                className="flex-1"
                            >
                                {navigation.previous ? (
                                    <Link href={`/admin/reviews/${navigation.previous.id}`}>
                                        <ChevronLeft className="h-4 w-4 mr-1" />
                                        Prev
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronLeft className="h-4 w-4 mr-1" />
                                        Prev
                                    </span>
                                )}
                            </Button>
                            
                            <Button 
                                variant="outline" 
                                size="sm" 
                                asChild
                                disabled={!navigation.next}
                                className="flex-1"
                            >
                                {navigation.next ? (
                                    <Link href={`/admin/reviews/${navigation.next.id}`}>
                                        Next
                                        <ChevronRight className="h-4 w-4 ml-1" />
                                    </Link>
                                ) : (
                                    <span>
                                        Next
                                        <ChevronRight className="h-4 w-4 ml-1" />
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
                                        <Check className="h-4 w-4 mr-1" />
                                        Approve
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(false)}
                                        disabled={isUpdating}
                                        className="flex-1"
                                    >
                                        <X className="h-4 w-4 mr-1" />
                                        Reject
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
                                    <X className="h-4 w-4 mr-1" />
                                    Unapprove
                                </Button>
                            )}
                        </div>
                    </div>
                </div>
            </div>

            {/* Bottom Padding */}
            <div className="py-10">
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
                        <Button variant="destructive" onClick={handleDelete}>
                            Delete
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}

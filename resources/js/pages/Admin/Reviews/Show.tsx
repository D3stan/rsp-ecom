import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { useToast } from '@/contexts/ToastContext';
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
                    },
                },
            );
        } catch {
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
                },
            });
        } catch {
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
                                <ArrowLeft className="mr-1 h-4 w-4" />
                                Back to Reviews
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Review Details</h1>
                            <p className="text-sm text-muted-foreground md:text-base">Review by {review.user?.name || 'Guest User'}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Badge className={statusColors[review.is_approved ? 'approved' : 'pending']} variant="secondary">
                            {review.is_approved ? 'Approved' : 'Pending'}
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
                                <h2 className="mb-3 text-lg font-semibold">Rating</h2>
                                {renderStars(review.rating)}
                            </div>

                            {/* Review Comment */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">Review</h2>
                                <div className="rounded-lg bg-muted/50 p-4">
                                    <p className="text-sm leading-relaxed md:text-base">{review.comment || 'No comment provided'}</p>
                                </div>
                            </div>

                            {/* Product Information */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">Product</h2>
                                <div className="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                                    <Package className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <Link href={`/admin/products/${review.product.id}`} className="font-medium text-primary hover:underline">
                                            {review.product.name}
                                        </Link>
                                        <p className="text-sm text-muted-foreground">Product ID: {review.product.id}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Customer Information */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">Customer</h2>
                                <div className="flex items-center gap-3 rounded-lg bg-muted/30 p-3">
                                    <User className="h-8 w-8 text-muted-foreground" />
                                    <div>
                                        <p className="font-medium">{review.user?.name || 'Guest User'}</p>
                                        <p className="text-sm text-muted-foreground">{review.user?.email || 'No email provided'}</p>
                                    </div>
                                </div>
                            </div>

                            {/* Dates */}
                            <div>
                                <h2 className="mb-3 text-lg font-semibold">Timeline</h2>
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
                    <Card className="h-fit p-6">
                        <h2 className="mb-4 text-lg font-semibold">Actions</h2>
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
                                        Approve Review
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(false)}
                                        disabled={isUpdating}
                                        className="w-full"
                                    >
                                        <X className="mr-2 h-4 w-4" />
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
                                    <X className="mr-2 h-4 w-4" />
                                    Unapprove Review
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
                                    Delete Review
                                </Button>
                            </div>
                        </div>

                        {/* Quick Stats */}
                        <div className="mt-6 border-t pt-6">
                            <h3 className="mb-3 text-sm font-medium text-muted-foreground">Quick Info</h3>
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
                <div className="fixed right-0 bottom-0 left-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 md:hidden">
                    <div className="safe-area-inset-bottom">
                        {/* Navigation Row */}
                        <div className="flex gap-2 border-b border-border/50 p-2">
                            <Button variant="outline" size="sm" asChild disabled={!navigation.previous} className="flex-1">
                                {navigation.previous ? (
                                    <Link href={`/admin/reviews/${navigation.previous.id}`}>
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                        Prev
                                    </Link>
                                ) : (
                                    <span>
                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                        Prev
                                    </span>
                                )}
                            </Button>

                            <Button variant="outline" size="sm" asChild disabled={!navigation.next} className="flex-1">
                                {navigation.next ? (
                                    <Link href={`/admin/reviews/${navigation.next.id}`}>
                                        Next
                                        <ChevronRight className="ml-1 h-4 w-4" />
                                    </Link>
                                ) : (
                                    <span>
                                        Next
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
                                        Approve
                                    </Button>
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => handleStatusUpdate(false)}
                                        disabled={isUpdating}
                                        className="flex-1"
                                    >
                                        <X className="mr-1 h-4 w-4" />
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
                                    <X className="mr-1 h-4 w-4" />
                                    Unapprove
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
                        <DialogTitle>Delete Review</DialogTitle>
                        <DialogDescription>Are you sure you want to delete this review? This action cannot be undone.</DialogDescription>
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

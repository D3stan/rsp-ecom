import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { Star, ArrowLeft, Package } from 'lucide-react';
import React, { useState } from 'react';

interface Product {
    id: number;
    name: string;
    image_url?: string;
    price: number;
}

interface ReviewCreatePageProps {
    product: Product;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: route('dashboard'),
    },
    {
        title: 'My Orders',
        href: route('orders.index'),
    },
    {
        title: 'Write Review',
        href: '',
    },
];

const ReviewCreatePage: React.FC<ReviewCreatePageProps> = ({ product }) => {
    const [rating, setRating] = useState(0);
    const [hoveredRating, setHoveredRating] = useState(0);

    const { data, setData, post, processing, errors } = useForm({
        product_id: product.id,
        rating: 0,
        comment: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        if (rating === 0) return;
        
        setData('rating', rating);
        post(route('reviews.store'));
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', { 
            style: 'currency', 
            currency: 'EUR' 
        }).format(amount);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Write Review for ${product.name}`} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <a href={route('orders.index')}>
                            <ArrowLeft className="h-4 w-4" />
                        </a>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Write Review</h1>
                        <p className="text-muted-foreground">
                            Share your experience with this product
                        </p>
                    </div>
                </div>

                <div className="max-w-2xl mx-auto">
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Information</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="flex gap-4 p-4 border rounded-lg bg-muted/30">
                                <div className="flex-shrink-0">
                                    {product.image_url ? (
                                        <img
                                            src={product.image_url}
                                            alt={product.name}
                                            className="h-20 w-20 rounded-md object-cover"
                                        />
                                    ) : (
                                        <div className="h-20 w-20 rounded-md bg-muted flex items-center justify-center">
                                            <Package className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                    )}
                                </div>
                                <div className="flex-1">
                                    <h3 className="font-medium">{product.name}</h3>
                                    <p className="text-sm text-muted-foreground mt-1">
                                        {formatCurrency(product.price)}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card className="mt-6">
                        <CardHeader>
                            <CardTitle>Your Review</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleSubmit} className="space-y-6">
                                {/* Star Rating */}
                                <div className="space-y-2">
                                    <Label htmlFor="rating">Rating *</Label>
                                    <div className="flex gap-1">
                                        {[1, 2, 3, 4, 5].map((star) => (
                                            <button
                                                key={star}
                                                type="button"
                                                className="p-1 transition-colors"
                                                onMouseEnter={() => setHoveredRating(star)}
                                                onMouseLeave={() => setHoveredRating(0)}
                                                onClick={() => setRating(star)}
                                            >
                                                <Star
                                                    className={`h-8 w-8 ${
                                                        star <= (hoveredRating || rating)
                                                            ? 'fill-yellow-400 text-yellow-400'
                                                            : 'text-gray-300'
                                                    }`}
                                                />
                                            </button>
                                        ))}
                                    </div>
                                    {rating > 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            {rating === 1 && 'Poor'}
                                            {rating === 2 && 'Fair'}
                                            {rating === 3 && 'Good'}
                                            {rating === 4 && 'Very Good'}
                                            {rating === 5 && 'Excellent'}
                                        </p>
                                    )}
                                    {errors.rating && (
                                        <p className="text-sm text-red-600">{errors.rating}</p>
                                    )}
                                </div>

                                {/* Comment */}
                                <div className="space-y-2">
                                    <Label htmlFor="comment">Your Review (Optional)</Label>
                                    <Textarea
                                        id="comment"
                                        placeholder="Tell others about your experience with this product..."
                                        value={data.comment}
                                        onChange={(e) => setData('comment', e.target.value)}
                                        rows={4}
                                        maxLength={1000}
                                        className="resize-none"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {data.comment.length}/1000 characters
                                    </p>
                                    {errors.comment && (
                                        <p className="text-sm text-red-600">{errors.comment}</p>
                                    )}
                                </div>

                                {/* Submit Buttons */}
                                <div className="flex gap-3 pt-4">
                                    <Button
                                        type="submit"
                                        disabled={processing || rating === 0}
                                        className="flex-1"
                                    >
                                        {processing ? 'Submitting...' : 'Submit Review'}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        asChild
                                        className="flex-1"
                                    >
                                        <a href={route('orders.index')}>
                                            Cancel
                                        </a>
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

import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AdminLayout from '@/layouts/admin-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Edit2, Package, DollarSign, Star, TrendingUp, Percent, Plus, MessageSquare } from 'lucide-react';

interface Product {
    id: number;
    name: string;
    slug: string;
    description?: string;
    price: number;
    compare_price?: number;
    stock_quantity: number;
    sku?: string;
    images: string[];
    status: 'active' | 'inactive' | 'draft';
    featured: boolean;
    category_id?: number;
    size_id?: number;
    created_at: string;
    updated_at: string;
    category?: {
        id: number;
        name: string;
        slug: string;
    };
    size?: {
        id: number;
        name: string;
    };
    main_image_url: string;
    image_urls: string[];
    discount_percentage?: number;
    average_rating: number;
    review_count: number;
    reviews?: Array<{
        id: number;
        rating: number;
        comment: string;
        user: {
            name: string;
        };
        created_at: string;
    }>;
}

interface Props {
    product: Product;
}

export default function ProductsShow({ product }: Props) {
    const [isRestocking, setIsRestocking] = useState(false);
    const [isDiscounting, setIsDiscounting] = useState(false);
    const [isAddingReview, setIsAddingReview] = useState(false);

    // Quick restock form
    const { data: restockData, setData: setRestockData, put: restockPut, processing: restockProcessing } = useForm({
        stock_quantity: product.stock_quantity.toString(),
    });

    // Quick discount form  
    const { data: discountData, setData: setDiscountData, put: discountPut, processing: discountProcessing } = useForm({
        compare_price: product.compare_price?.toString() || '',
    });

    // Add review form
    const { data: reviewData, setData: setReviewData, post: reviewPost, processing: reviewProcessing, reset: reviewReset } = useForm({
        rating: '5',
        comment: '',
        user_name: '',
    });

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'default',
            inactive: 'secondary', 
            draft: 'outline',
        } as const;

        const colors = {
            active: 'bg-green-500',
            inactive: 'bg-gray-500',
            draft: 'bg-yellow-500',
        };

        return (
            <Badge variant={variants[status as keyof typeof variants]} className={colors[status as keyof typeof colors]}>
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    const getStockBadge = (stock: number) => {
        if (stock === 0) {
            return <Badge variant="destructive">Out of Stock</Badge>;
        }
        if (stock < 10) {
            return <Badge variant="outline" className="text-orange-600 border-orange-600">Low Stock</Badge>;
        }
        return <Badge variant="default" className="bg-green-600">In Stock</Badge>;
    };

    const handleQuickRestock = (e: React.FormEvent) => {
        e.preventDefault();
        restockPut(route('admin.products.quick-stock', product.id), {
            onSuccess: () => setIsRestocking(false),
        });
    };

    const handleQuickDiscount = (e: React.FormEvent) => {
        e.preventDefault();
        discountPut(route('admin.products.update', product.id), {
            onSuccess: () => setIsDiscounting(false),
        });
    };

    const handleAddReview = (e: React.FormEvent) => {
        e.preventDefault();
        reviewPost(route('admin.products.reviews.store', product.id), {
            onSuccess: () => {
                setIsAddingReview(false);
                reviewReset();
            },
        });
    };

    return (
        <AdminLayout>
            <Head title={`Product: ${product.name}`} />

            <div className="p-4 sm:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white">{product.name}</h1>
                        <p className="text-gray-500">SKU: {product.sku || 'N/A'}</p>
                    </div>
                    <Button size="sm" asChild>
                        <Link href={route('admin.products.edit', product.id)}>
                            <Edit2 className="mr-2 h-4 w-4" />
                            Edit
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Product Images */}
                    <Card>
                        <CardHeader className="pb-4">
                            <CardTitle className="text-lg">Product Images</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {product.image_urls.length > 0 ? (
                                <div className="space-y-4">
                                    <div className="aspect-square rounded-lg overflow-hidden bg-gray-100">
                                        <img 
                                            src={product.main_image_url} 
                                            alt={product.name}
                                            className="w-full h-full object-cover"
                                            onError={(e) => {
                                                e.currentTarget.src = '/images/product.png';
                                            }}
                                        />
                                    </div>
                                    {product.image_urls.length > 1 && (
                                        <div className="grid grid-cols-4 gap-2">
                                            {product.image_urls.slice(1).map((image, index) => (
                                                <div key={index} className="aspect-square rounded-lg overflow-hidden bg-gray-100">
                                                    <img 
                                                        src={image} 
                                                        alt={`${product.name} ${index + 2}`}
                                                        className="w-full h-full object-cover"
                                                        onError={(e) => {
                                                            e.currentTarget.src = '/images/product.png';
                                                        }}
                                                    />
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="aspect-square rounded-lg bg-gray-100 flex items-center justify-center">
                                    <Package className="h-12 w-12 text-gray-400" />
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Product Information */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Package className="h-5 w-5 text-blue-600" />
                                <CardTitle className="text-lg">Product Information</CardTitle>
                            </div>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="text-sm font-medium text-gray-500">Status</span>
                                    <div className="mt-1">{getStatusBadge(product.status)}</div>
                                </div>
                                
                                <div>
                                    <span className="text-sm font-medium text-gray-500">Stock</span>
                                    <div className="mt-1 flex items-center gap-2">
                                        <span className="font-bold">{product.stock_quantity}</span>
                                        {getStockBadge(product.stock_quantity)}
                                    </div>
                                </div>
                                
                                <div>
                                    <span className="text-sm font-medium text-gray-500">Category</span>
                                    <div className="mt-1">{product.category?.name || 'No category'}</div>
                                </div>
                                
                                <div>
                                    <span className="text-sm font-medium text-gray-500">Size</span>
                                    <div className="mt-1">{product.size?.name || 'No size'}</div>
                                </div>
                            </div>

                            {product.featured && (
                                <div className="mt-4">
                                    <Badge variant="outline" className="text-yellow-600 border-yellow-600">
                                        <Star className="mr-1 h-3 w-3" />
                                        Featured Product
                                    </Badge>
                                </div>
                            )}

                            {/* Quick Restock */}
                            <div className="mt-6 pt-4 border-t">
                                {!isRestocking ? (
                                    <Button 
                                        variant="outline" 
                                        size="sm"
                                        onClick={() => setIsRestocking(true)}
                                        className="w-full"
                                    >
                                        <TrendingUp className="mr-2 h-4 w-4" />
                                        Quick Restock
                                    </Button>
                                ) : (
                                    <form onSubmit={handleQuickRestock} className="space-y-3">
                                        <Label htmlFor="stock_quantity" className="text-sm">New Stock Quantity</Label>
                                        <Input
                                            id="stock_quantity"
                                            type="number"
                                            value={restockData.stock_quantity}
                                            onChange={(e) => setRestockData('stock_quantity', e.target.value)}
                                            placeholder="Enter new stock quantity"
                                        />
                                        <div className="flex gap-2">
                                            <Button 
                                                type="submit" 
                                                size="sm" 
                                                disabled={restockProcessing}
                                                className="flex-1"
                                            >
                                                {restockProcessing ? 'Updating...' : 'Update Stock'}
                                            </Button>
                                            <Button 
                                                type="button" 
                                                variant="outline" 
                                                size="sm"
                                                onClick={() => setIsRestocking(false)}
                                            >
                                                Cancel
                                            </Button>
                                        </div>
                                    </form>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>

                {/* Pricing */}
                <Card>
                    <CardHeader className="pb-4">
                        <div className="flex items-center gap-2">
                            <DollarSign className="h-5 w-5 text-green-600" />
                            <CardTitle className="text-lg">Pricing</CardTitle>
                        </div>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <span className="text-sm font-medium text-gray-500">Current Price</span>
                                <div className="mt-1 text-2xl font-bold">{formatCurrency(product.price)}</div>
                            </div>
                            
                            {product.compare_price && product.compare_price > product.price && (
                                <>
                                    <div>
                                        <span className="text-sm font-medium text-gray-500">Compare Price</span>
                                        <div className="mt-1 text-lg line-through text-gray-500">
                                            {formatCurrency(product.compare_price)}
                                        </div>
                                    </div>
                                    {product.discount_percentage && (
                                        <div>
                                            <span className="text-sm font-medium text-gray-500">Discount</span>
                                            <div className="mt-1">
                                                <Badge variant="destructive" className="text-lg">
                                                    -{product.discount_percentage}%
                                                </Badge>
                                            </div>
                                        </div>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Quick Discount */}
                        <div className="mt-6 pt-4 border-t">
                            {!isDiscounting ? (
                                <Button 
                                    variant="outline" 
                                    size="sm"
                                    onClick={() => setIsDiscounting(true)}
                                    className="w-full"
                                >
                                    <Percent className="mr-2 h-4 w-4" />
                                    Quick Discount
                                </Button>
                            ) : (
                                <form onSubmit={handleQuickDiscount} className="space-y-3">
                                    <Label htmlFor="compare_price" className="text-sm">Compare Price (for discount)</Label>
                                    <Input
                                        id="compare_price"
                                        type="number"
                                        step="0.01"
                                        value={discountData.compare_price}
                                        onChange={(e) => setDiscountData('compare_price', e.target.value)}
                                        placeholder="Enter compare price"
                                    />
                                    <div className="flex gap-2">
                                        <Button 
                                            type="submit" 
                                            size="sm" 
                                            disabled={discountProcessing}
                                            className="flex-1"
                                        >
                                            {discountProcessing ? 'Updating...' : 'Set Discount'}
                                        </Button>
                                        <Button 
                                            type="button" 
                                            variant="outline" 
                                            size="sm"
                                            onClick={() => setIsDiscounting(false)}
                                        >
                                            Cancel
                                        </Button>
                                    </div>
                                </form>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Description */}
                {product.description && (
                    <Card>
                        <CardHeader className="pb-4">
                            <CardTitle className="text-lg">Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-gray-600 whitespace-pre-wrap">
                                {product.description}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Reviews */}
                <Card>
                    <CardHeader className="pb-4">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Star className="h-5 w-5 text-yellow-500" />
                                <CardTitle className="text-lg">Reviews</CardTitle>
                            </div>
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => setIsAddingReview(true)}
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                Add Review
                            </Button>
                        </div>
                        <CardDescription>
                            Customer reviews and ratings for this product
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Review Summary */}
                        <div className="flex items-center gap-4 p-4 bg-gray-50 rounded-lg">
                            <div className="flex items-center gap-1">
                                <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
                                <span className="font-bold text-lg">
                                    {product.average_rating.toFixed(1)}
                                </span>
                            </div>
                            <div className="text-gray-600">
                                {product.review_count} {product.review_count === 1 ? 'review' : 'reviews'}
                            </div>
                        </div>

                        {/* Add Review Form */}
                        {isAddingReview && (
                            <form onSubmit={handleAddReview} className="space-y-4 p-4 border rounded-lg bg-gray-50">
                                <h4 className="font-medium">Add New Review</h4>
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="user_name">Customer Name</Label>
                                        <Input
                                            id="user_name"
                                            value={reviewData.user_name}
                                            onChange={(e) => setReviewData('user_name', e.target.value)}
                                            placeholder="Enter customer name"
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="rating">Rating</Label>
                                        <Select value={reviewData.rating} onValueChange={(value) => setReviewData('rating', value)}>
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {[5, 4, 3, 2, 1].map((rating) => (
                                                    <SelectItem key={rating} value={rating.toString()}>
                                                        <div className="flex items-center gap-1">
                                                            {Array.from({ length: rating }, (_, i) => (
                                                                <Star key={i} className="h-4 w-4 fill-yellow-400 text-yellow-400" />
                                                            ))}
                                                            <span className="ml-2">{rating} Stars</span>
                                                        </div>
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="comment">Review Comment</Label>
                                    <Textarea
                                        id="comment"
                                        value={reviewData.comment}
                                        onChange={(e) => setReviewData('comment', e.target.value)}
                                        placeholder="Enter review comment"
                                        rows={3}
                                        required
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button 
                                        type="submit" 
                                        size="sm" 
                                        disabled={reviewProcessing}
                                        className="flex-1"
                                    >
                                        {reviewProcessing ? 'Adding...' : 'Add Review'}
                                    </Button>
                                    <Button 
                                        type="button" 
                                        variant="outline" 
                                        size="sm"
                                        onClick={() => setIsAddingReview(false)}
                                    >
                                        Cancel
                                    </Button>
                                </div>
                            </form>
                        )}

                        {/* Recent Reviews */}
                        {product.reviews && product.reviews.length > 0 ? (
                            <div className="space-y-4">
                                {product.reviews.slice(0, 5).map((review) => (
                                    <div key={review.id} className="border-b pb-4 last:border-b-0">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{review.user.name}</span>
                                                <div className="flex items-center">
                                                    {Array.from({ length: 5 }, (_, i) => (
                                                        <Star
                                                            key={i}
                                                            className={`h-4 w-4 ${
                                                                i < review.rating
                                                                    ? 'fill-yellow-400 text-yellow-400'
                                                                    : 'text-gray-300'
                                                            }`}
                                                        />
                                                    ))}
                                                </div>
                                            </div>
                                            <span className="text-sm text-gray-500">
                                                {new Date(review.created_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <p className="text-gray-600">{review.comment}</p>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <div className="text-center py-8 text-gray-500">
                                <MessageSquare className="h-12 w-12 mx-auto mb-4 text-gray-300" />
                                <p>No reviews yet</p>
                                <p className="text-sm">Be the first to add a review for this product</p>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}

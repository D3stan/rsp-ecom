import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Edit, ArrowLeft, Package, DollarSign, Star } from 'lucide-react';

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
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount);
    };

    const getStatusBadge = (status: string) => {
        const variants = {
            active: 'default',
            inactive: 'secondary',
            draft: 'outline',
        } as const;

        return (
            <Badge variant={variants[status as keyof typeof variants]}>
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

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/admin/dashboard' },
                { title: 'Products', href: '/admin/products' },
                { title: product.name, href: `/admin/products/${product.id}` },
            ]}
        >
            <Head title={`Product: ${product.name}`} />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex flex-col space-y-4 md:flex-row md:items-center md:justify-between md:space-y-0">
                    <div className="flex items-center space-x-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href="/admin/products">
                                <ArrowLeft className="mr-2 h-4 w-4" />
                                Back to Products
                            </Link>
                        </Button>
                        <div>
                            <h1 className="text-2xl font-bold tracking-tight">{product.name}</h1>
                            <p className="text-muted-foreground">
                                SKU: {product.sku || 'N/A'}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-col space-y-2 sm:flex-row sm:space-x-2 sm:space-y-0">
                        <Button asChild>
                            <Link href={`/admin/products/${product.id}/edit`}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit Product
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 md:grid-cols-2">
                    {/* Product Images */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Product Images</CardTitle>
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

                    {/* Product Details */}
                    <div className="space-y-6">
                        {/* Basic Info */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Product Information</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Status:</span>
                                    <div className="flex items-center space-x-2">
                                        {getStatusBadge(product.status)}
                                        {product.featured && (
                                            <Badge variant="outline" className="text-yellow-600 border-yellow-600">
                                                Featured
                                            </Badge>
                                        )}
                                    </div>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Category:</span>
                                    <span>{product.category?.name || 'No category'}</span>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Size:</span>
                                    <span>{product.size?.name || 'No size'}</span>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Stock:</span>
                                    <div className="flex items-center space-x-2">
                                        <span className="font-bold">{product.stock_quantity}</span>
                                        {getStockBadge(product.stock_quantity)}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {/* Pricing */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <DollarSign className="mr-2 h-5 w-5" />
                                    Pricing
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="font-medium">Price:</span>
                                    <span className="text-2xl font-bold">{formatCurrency(product.price)}</span>
                                </div>
                                
                                {product.compare_price && product.compare_price > product.price && (
                                    <>
                                        <div className="flex items-center justify-between">
                                            <span className="font-medium">Compare Price:</span>
                                            <span className="text-lg line-through text-muted-foreground">
                                                {formatCurrency(product.compare_price)}
                                            </span>
                                        </div>
                                        {product.discount_percentage && (
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium">Discount:</span>
                                                <Badge variant="destructive">
                                                    -{product.discount_percentage}%
                                                </Badge>
                                            </div>
                                        )}
                                    </>
                                )}
                            </CardContent>
                        </Card>

                        {/* Reviews Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center">
                                    <Star className="mr-2 h-5 w-5" />
                                    Reviews
                                </CardTitle>
                            </CardHeader>
                            <CardContent>
                                <div className="flex items-center space-x-4">
                                    <div className="flex items-center space-x-1">
                                        <Star className="h-5 w-5 fill-yellow-400 text-yellow-400" />
                                        <span className="font-bold text-lg">
                                            {product.average_rating.toFixed(1)}
                                        </span>
                                    </div>
                                    <div className="text-muted-foreground">
                                        {product.review_count} {product.review_count === 1 ? 'review' : 'reviews'}
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Description */}
                {product.description && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Description</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-muted-foreground whitespace-pre-wrap">
                                {product.description}
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Recent Reviews */}
                {product.reviews && product.reviews.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Reviews</CardTitle>
                            <CardDescription>
                                Latest customer reviews for this product
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {product.reviews.slice(0, 5).map((review) => (
                                    <div key={review.id} className="border-b pb-4 last:border-b-0">
                                        <div className="flex items-center justify-between mb-2">
                                            <div className="flex items-center space-x-2">
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
                                            <span className="text-sm text-muted-foreground">
                                                {new Date(review.created_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                        <p className="text-muted-foreground">{review.comment}</p>
                                    </div>
                                ))}
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </AdminLayout>
    );
}

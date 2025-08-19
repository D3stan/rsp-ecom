import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import {
    AlertTriangle,
    ChevronDown,
    ChevronUp,
    DollarSign,
    Edit,
    Eye,
    Filter,
    Minus,
    MoreHorizontal,
    Package,
    Plus,
    Search,
    Tag,
    Trash2,
    TrendingUp,
    Zap,
} from 'lucide-react';
import { useEffect, useState } from 'react';

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
    discount_percentage?: number;
    average_rating: number;
    review_count: number;
}

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    products: {
        data: Product[];
        meta: {
            total: number;
            per_page: number;
            current_page: number;
            last_page: number;
            from: number;
            to: number;
        };
        links: PaginationLink[];
    };
    categories: Category[];
    filters: {
        search?: string;
        category?: string;
        status?: string;
        stock_filter?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        page?: string;
    };
    stats: {
        total_products: number;
        active_products: number;
        low_stock_count: number;
        total_value: number;
    };
}

const statusColors = {
    active: 'bg-green-100 text-green-800',
    inactive: 'bg-gray-100 text-gray-800',
    draft: 'bg-yellow-100 text-yellow-800',
};

export default function ProductsIndex({ products, categories, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [categoryFilter, setCategoryFilter] = useState(filters.category || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [stockFilter, setStockFilter] = useState(filters.stock_filter || '');
    const [isStatsOpen, setIsStatsOpen] = useState(false);

    // Add safety checks for products data
    const productsData = products?.data || [];
    const productsMeta = products?.meta || { total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 };
    const productsLinks = products?.links || [];

    // Instant filtering - apply filters immediately on change
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get(
                '/admin/products',
                {
                    search: search || undefined,
                    category: categoryFilter || undefined,
                    status: statusFilter || undefined,
                    stock_filter: stockFilter || undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300); // 300ms debounce for search

        return () => clearTimeout(timeoutId);
    }, [search, categoryFilter, statusFilter, stockFilter]);

    const clearFilters = () => {
        setSearch('');
        setCategoryFilter('');
        setStatusFilter('');
        setStockFilter('');
        router.get('/admin/products');
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('it-IT', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getStatusBadge = (status: string) => {
        return (
            <Badge className={statusColors[status as keyof typeof statusColors]} variant="secondary">
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </Badge>
        );
    };

    const getStockBadge = (stock: number) => {
        if (stock === 0) {
            return (
                <Badge className="bg-red-100 text-red-800" variant="secondary">
                    Out of Stock
                </Badge>
            );
        }
        if (stock < 10) {
            return (
                <Badge className="bg-orange-100 text-orange-800" variant="secondary">
                    Low Stock
                </Badge>
            );
        }
        return (
            <Badge className="bg-green-100 text-green-800" variant="secondary">
                In Stock
            </Badge>
        );
    };

    const handleQuickStock = (productId: number, increment: boolean) => {
        router.patch(
            `/admin/products/${productId}/quick-stock`,
            {
                increment,
            },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handleDeleteProduct = (productId: number, productName: string) => {
        if (window.confirm(`Are you sure you want to delete "${productName}"? This action cannot be undone.`)) {
            router.delete(`/admin/products/${productId}`, {
                preserveState: true,
                onSuccess: () => {
                    // Optional: Show success message or refresh data
                },
                onError: (errors) => {
                    console.error('Error deleting product:', errors);
                }
            });
        }
    };

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Products', href: '/admin/products' },
            ]}
        >
            <Head title="Products - Admin" />

            <div className="space-y-4 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Products</h1>
                        <p className="text-sm text-muted-foreground md:text-base">Manage your product catalog</p>
                    </div>
                    <Button asChild>
                        <Link href="/admin/products/create">
                            <Plus className="mr-2 h-4 w-4" />
                            Add Product
                        </Link>
                    </Button>
                </div>

                {/* Collapsible Stats Cards */}
                <Collapsible open={isStatsOpen} onOpenChange={setIsStatsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" className="w-full justify-between" size="sm">
                            <span className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" />
                                Quick Stats
                            </span>
                            {isStatsOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-4 space-y-4">
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Package className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">Total</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{stats.total_products}</p>
                                    <p className="text-xs text-muted-foreground">{stats.active_products} active</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <DollarSign className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">Value</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{formatCurrency(stats.total_value)}</p>
                                    <p className="text-xs text-muted-foreground">Inventory</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <AlertTriangle className="h-4 w-4 text-orange-600" />
                                    <h3 className="text-xs font-medium">Low Stock</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold text-orange-600">{stats.low_stock_count}</p>
                                    <p className="text-xs text-muted-foreground">Need attention</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Tag className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">Categories</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{categories.length}</p>
                                    <p className="text-xs text-muted-foreground">Active</p>
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
                            <Input placeholder="Search products..." value={search} onChange={(e) => setSearch(e.target.value)} className="pl-10" />
                        </div>

                        {/* Filter Dropdowns */}
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-4">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">Category</label>
                                <select
                                    value={categoryFilter}
                                    onChange={(e) => setCategoryFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Categories</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.slug}>
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">Status</label>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Status</option>
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                    <option value="draft">Draft</option>
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">Stock</label>
                                <select
                                    value={stockFilter}
                                    onChange={(e) => setStockFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">All Stock</option>
                                    <option value="in-stock">In Stock</option>
                                    <option value="low-stock">Low Stock</option>
                                    <option value="out-of-stock">Out of Stock</option>
                                </select>
                            </div>

                            <div className="flex items-end">
                                {(search || categoryFilter || statusFilter || stockFilter) && (
                                    <Button onClick={clearFilters} variant="outline" size="sm" className="w-full">
                                        Clear Filters
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Mobile-First Products Table */}
                <Card>
                    {/* Desktop Table View */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-4 text-left font-medium">Product</th>
                                    <th className="p-4 text-left font-medium">Category</th>
                                    <th className="p-4 text-left font-medium">Price</th>
                                    <th className="p-4 text-left font-medium">Stock</th>
                                    <th className="p-4 text-left font-medium">Status</th>
                                    <th className="p-4 text-left font-medium">Date</th>
                                    <th className="p-4 text-right font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {productsData.length > 0 ? (
                                    productsData.map((product) => (
                                        <tr key={product.id} className="border-b hover:bg-muted/50">
                                            <td className="p-4">
                                                <div className="flex items-center space-x-3">
                                                    <div className="h-10 w-10 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                                        <img
                                                            src={product.main_image_url}
                                                            alt={product.name}
                                                            className="h-full w-full object-cover"
                                                            onError={(e) => {
                                                                e.currentTarget.src = '/images/product.png';
                                                            }}
                                                        />
                                                    </div>
                                                    <div>
                                                        <Link
                                                            href={`/admin/products/${product.id}`}
                                                            className="font-medium text-primary hover:underline"
                                                        >
                                                            {product.name}
                                                        </Link>
                                                        <p className="text-sm text-muted-foreground">{product.sku || 'No SKU'}</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <span className="text-sm">{product.category?.name || 'No category'}</span>
                                            </td>
                                            <td className="p-4">
                                                <div>
                                                    <span className="font-medium">{formatCurrency(product.price)}</span>
                                                    {product.compare_price && product.compare_price > product.price && (
                                                        <p className="text-xs text-muted-foreground line-through">
                                                            {formatCurrency(product.compare_price)}
                                                        </p>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <div className="flex items-center space-x-2">
                                                    <span className="font-medium">{product.stock_quantity}</span>
                                                    {getStockBadge(product.stock_quantity)}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <div className="space-y-1">
                                                    {getStatusBadge(product.status)}
                                                    {product.featured && (
                                                        <div>
                                                            <Badge className="bg-yellow-100 text-yellow-800" variant="secondary">
                                                                Featured
                                                            </Badge>
                                                        </div>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <span className="text-sm">{formatDate(product.created_at)}</span>
                                            </td>
                                            <td className="p-4 text-right">
                                                <div className="flex items-center justify-end space-x-1">
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleQuickStock(product.id, false)}
                                                        className="h-7 w-7 p-0"
                                                    >
                                                        <Minus className="h-3 w-3" />
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        onClick={() => handleQuickStock(product.id, true)}
                                                        className="h-7 w-7 p-0"
                                                    >
                                                        <Plus className="h-3 w-3" />
                                                    </Button>
                                                    <DropdownMenu>
                                                        <DropdownMenuTrigger asChild>
                                                            <Button variant="ghost" size="sm">
                                                                <MoreHorizontal className="h-4 w-4" />
                                                            </Button>
                                                        </DropdownMenuTrigger>
                                                        <DropdownMenuContent align="end">
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/admin/products/${product.id}`}>
                                                                    <Eye className="mr-2 h-4 w-4" />
                                                                    View Details
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuItem asChild>
                                                                <Link href={`/admin/products/${product.id}/edit`}>
                                                                    <Edit className="mr-2 h-4 w-4" />
                                                                    Edit Product
                                                                </Link>
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem>
                                                                <Zap className="mr-2 h-4 w-4" />
                                                                Flash Discount
                                                            </DropdownMenuItem>
                                                            <DropdownMenuSeparator />
                                                            <DropdownMenuItem 
                                                                className="text-red-600"
                                                                onClick={() => handleDeleteProduct(product.id, product.name)}
                                                            >
                                                                <Trash2 className="mr-2 h-4 w-4" />
                                                                Delete Product
                                                            </DropdownMenuItem>
                                                        </DropdownMenuContent>
                                                    </DropdownMenu>
                                                </div>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="py-8 text-center text-muted-foreground">
                                            No products found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card View */}
                    <div className="space-y-3 p-3 md:hidden">
                        {productsData.length > 0 ? (
                            productsData.map((product) => (
                                <Card key={product.id} className="p-4">
                                    <div className="mb-3 flex items-start justify-between">
                                        <div className="flex items-center space-x-3">
                                            <div className="h-12 w-12 flex-shrink-0 overflow-hidden rounded-lg bg-gray-100">
                                                <img
                                                    src={product.main_image_url}
                                                    alt={product.name}
                                                    className="h-full w-full object-cover"
                                                    onError={(e) => {
                                                        e.currentTarget.src = '/images/product.png';
                                                    }}
                                                />
                                            </div>
                                            <div>
                                                <Link href={`/admin/products/${product.id}`} className="font-medium text-primary hover:underline">
                                                    {product.name}
                                                </Link>
                                                <p className="text-sm text-muted-foreground">{product.sku || 'No SKU'}</p>
                                            </div>
                                        </div>
                                        <div className="flex gap-2">
                                            {getStatusBadge(product.status)}
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm">
                                                        <MoreHorizontal className="h-4 w-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/admin/products/${product.id}`}>
                                                            <Eye className="mr-2 h-4 w-4" />
                                                            View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem asChild>
                                                        <Link href={`/admin/products/${product.id}/edit`}>
                                                            <Edit className="mr-2 h-4 w-4" />
                                                            Edit Product
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem>
                                                        <Zap className="mr-2 h-4 w-4" />
                                                        Flash Discount
                                                    </DropdownMenuItem>
                                                    <DropdownMenuSeparator />
                                                    <DropdownMenuItem 
                                                        className="text-red-600"
                                                        onClick={() => handleDeleteProduct(product.id, product.name)}
                                                    >
                                                        <Trash2 className="mr-2 h-4 w-4" />
                                                        Delete Product
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Category:</span>
                                            <span className="text-sm">{product.category?.name || 'No category'}</span>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Price:</span>
                                            <div className="text-right">
                                                <span className="font-bold">{formatCurrency(product.price)}</span>
                                                {product.compare_price && product.compare_price > product.price && (
                                                    <p className="text-xs text-muted-foreground line-through">
                                                        {formatCurrency(product.compare_price)}
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Stock:</span>
                                            <div className="flex items-center space-x-2">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleQuickStock(product.id, false)}
                                                    className="h-6 w-6 p-0"
                                                >
                                                    <Minus className="h-3 w-3" />
                                                </Button>
                                                <span className="text-sm font-bold">{product.stock_quantity}</span>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() => handleQuickStock(product.id, true)}
                                                    className="h-6 w-6 p-0"
                                                >
                                                    <Plus className="h-3 w-3" />
                                                </Button>
                                            </div>
                                        </div>

                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-muted-foreground">Stock Status:</span>
                                            {getStockBadge(product.stock_quantity)}
                                        </div>

                                        {product.featured && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">Featured:</span>
                                                <Badge className="bg-yellow-100 text-yellow-800" variant="secondary">
                                                    Featured
                                                </Badge>
                                            </div>
                                        )}
                                    </div>
                                </Card>
                            ))
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">No products found</div>
                        )}
                    </div>

                    {/* Mobile-First Pagination */}
                    {productsMeta.last_page > 1 && (
                        <div className="border-t px-4 py-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-center text-sm text-muted-foreground md:text-left">
                                    Showing {productsMeta.from} to {productsMeta.to} of {productsMeta.total} results
                                </p>
                                <div className="flex items-center justify-center space-x-1 md:space-x-2">
                                    {productsLinks.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? 'default' : 'outline'}
                                            size="sm"
                                            onClick={() => link.url && router.get(link.url)}
                                            disabled={!link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className="h-8 min-w-[32px]"
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </Card>

                {/* Mobile Bottom Navigation */}
                <div className="safe-area-pb fixed right-0 bottom-0 left-0 border-t bg-background p-4 md:hidden">
                    <div className="flex justify-center gap-4">
                        <Button variant="outline" size="sm" onClick={() => setIsStatsOpen(!isStatsOpen)} className="flex-1">
                            <TrendingUp className="mr-2 h-4 w-4" />
                            Stats
                        </Button>
                        <Button variant="outline" size="sm" onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })} className="flex-1">
                            <Filter className="mr-2 h-4 w-4" />
                            Filters
                        </Button>
                        <Button variant="outline" size="sm" asChild className="flex-1">
                            <Link href="/admin/products/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Add
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Add bottom padding to account for fixed navigation */}
                <div className="h-20 md:hidden"></div>
            </div>
        </AdminLayout>
    );
}

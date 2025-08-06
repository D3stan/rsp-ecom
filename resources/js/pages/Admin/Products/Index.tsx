import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AdminLayout from '@/layouts/admin-layout';
import { DataTable } from '@/components/ui/data-table';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { 
    DropdownMenu, 
    DropdownMenuContent, 
    DropdownMenuItem, 
    DropdownMenuLabel, 
    DropdownMenuSeparator, 
    DropdownMenuTrigger 
} from '@/components/ui/dropdown-menu';
import { 
    Plus, 
    Filter, 
    Search, 
    MoreVertical, 
    Edit, 
    Trash2, 
    Eye, 
    Package,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    DollarSign,
    Zap,
    Tag,
    BarChart3
} from 'lucide-react';

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
        slug: string;
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

interface Size {
    id: number;
    name: string;
    slug: string;
}

interface Props {
    products: {
        data: Product[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
    };
    categories: Category[];
    sizes: Size[];
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

export default function ProductsIndex({ products, categories, sizes, filters, stats }: Props) {
    const [searchTerm, setSearchTerm] = useState(filters.search || '');
    const [selectedCategory, setSelectedCategory] = useState(filters.category || 'all');
    const [selectedStatus, setSelectedStatus] = useState(filters.status || 'all');
    const [selectedStockFilter, setSelectedStockFilter] = useState(filters.stock_filter || 'all');

    const handleSearch = (value: string) => {
        setSearchTerm(value);
        updateFilters({ search: value });
    };

    const updateFilters = (newFilters: Partial<typeof filters>) => {
        const params = new URLSearchParams();
        
        Object.entries({ ...filters, ...newFilters }).forEach(([key, value]) => {
            if (value && value !== 'all') {
                params.append(key, value);
            }
        });

        router.get(route('admin.products.index'), Object.fromEntries(params), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        updateFilters({ page: page.toString() });
    };

    const handleQuickStock = (productId: number, increment: boolean) => {
        router.patch(route('admin.products.quick-stock', productId), {
            increment,
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleFlashDiscount = (productId: number) => {
        // This would open a modal or redirect to a quick discount form
        console.log('Flash discount for product:', productId);
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

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount);
    };

    const columns = [
        {
            key: 'image',
            header: '',
            className: 'w-16',
            render: (product: Product) => (
                <div className="w-12 h-12 rounded-lg overflow-hidden bg-gray-100 flex-shrink-0">
                    <img 
                        src={product.main_image_url} 
                        alt={product.name}
                        className="w-full h-full object-cover"
                        onError={(e) => {
                            e.currentTarget.src = '/images/product.png';
                        }}
                    />
                </div>
            ),
        },
        {
            key: 'name',
            header: 'Product',
            render: (product: Product) => (
                <div className="space-y-1">
                    <p className="font-medium text-sm">{product.name}</p>
                    <p className="text-xs text-muted-foreground">{product.sku || 'No SKU'}</p>
                    {product.category && (
                        <Badge variant="outline" className="text-xs">
                            {product.category.name}
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'price',
            header: 'Price',
            className: 'text-right',
            render: (product: Product) => (
                <div className="text-right space-y-1">
                    <p className="font-medium">{formatCurrency(product.price)}</p>
                    {product.compare_price && product.compare_price > product.price && (
                        <div className="space-y-1">
                            <p className="text-xs text-muted-foreground line-through">
                                {formatCurrency(product.compare_price)}
                            </p>
                            {product.discount_percentage && (
                                <Badge variant="destructive" className="text-xs">
                                    -{product.discount_percentage}%
                                </Badge>
                            )}
                        </div>
                    )}
                </div>
            ),
        },
        {
            key: 'stock',
            header: 'Stock',
            className: 'text-center',
            render: (product: Product) => (
                <div className="space-y-2">
                    <p className="font-medium">{product.stock_quantity}</p>
                    {getStockBadge(product.stock_quantity)}
                    <div className="flex gap-1 justify-center">
                        <Button 
                            size="sm" 
                            variant="outline" 
                            className="h-6 w-6 p-0"
                            onClick={() => handleQuickStock(product.id, false)}
                        >
                            -
                        </Button>
                        <Button 
                            size="sm" 
                            variant="outline" 
                            className="h-6 w-6 p-0"
                            onClick={() => handleQuickStock(product.id, true)}
                        >
                            +
                        </Button>
                    </div>
                </div>
            ),
        },
        {
            key: 'status',
            header: 'Status',
            className: 'text-center',
            render: (product: Product) => (
                <div className="space-y-2">
                    {getStatusBadge(product.status)}
                    {product.featured && (
                        <Badge variant="outline" className="text-yellow-600 border-yellow-600">
                            <TrendingUp className="w-3 h-3 mr-1" />
                            Featured
                        </Badge>
                    )}
                </div>
            ),
        },
        {
            key: 'actions',
            header: 'Actions',
            className: 'text-right',
            render: (product: Product) => (
                <DropdownMenu>
                    <DropdownMenuTrigger asChild>
                        <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                            <MoreVertical className="h-4 w-4" />
                        </Button>
                    </DropdownMenuTrigger>
                    <DropdownMenuContent align="end">
                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                        <DropdownMenuItem asChild>
                            <Link href={route('admin.products.show', product.id)}>
                                <Eye className="mr-2 h-4 w-4" />
                                View
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuItem asChild>
                            <Link href={route('admin.products.edit', product.id)}>
                                <Edit className="mr-2 h-4 w-4" />
                                Edit
                            </Link>
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem onClick={() => handleFlashDiscount(product.id)}>
                            <Zap className="mr-2 h-4 w-4" />
                            Flash Discount
                        </DropdownMenuItem>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem className="text-destructive">
                            <Trash2 className="mr-2 h-4 w-4" />
                            Delete
                        </DropdownMenuItem>
                    </DropdownMenuContent>
                </DropdownMenu>
            ),
        },
    ];

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/admin/dashboard' },
                { title: 'Products', href: '/admin/products' },
            ]}
        >
            <Head title="Products Management" />

            <div className="space-y-6">
                {/* Page Header */}
                <div className="flex flex-col space-y-4 md:flex-row md:items-center md:justify-between md:space-y-0">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">Products</h1>
                        <p className="text-muted-foreground">
                            Manage your product catalog and inventory
                        </p>
                    </div>
                    <div className="flex flex-col space-y-2 sm:flex-row sm:space-x-2 sm:space-y-0">
                        <Button variant="outline" size="sm">
                            <Package className="mr-2 h-4 w-4" />
                            Import
                        </Button>
                        <Button asChild>
                            <Link href={route('admin.products.create')}>
                                <Plus className="mr-2 h-4 w-4" />
                                Add Product
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Products</CardTitle>
                            <Package className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_products}</div>
                            <p className="text-xs text-muted-foreground">
                                {stats.active_products} active
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Inventory Value</CardTitle>
                            <DollarSign className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatCurrency(stats.total_value)}</div>
                            <p className="text-xs text-muted-foreground">
                                Total stock value
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Low Stock</CardTitle>
                            <AlertTriangle className="h-4 w-4 text-orange-500" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">{stats.low_stock_count}</div>
                            <p className="text-xs text-muted-foreground">
                                Need attention
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Categories</CardTitle>
                            <Tag className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{categories.length}</div>
                            <p className="text-xs text-muted-foreground">
                                Active categories
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader>
                        <div className="flex flex-col space-y-4 md:flex-row md:items-center md:justify-between md:space-y-0">
                            <div className="flex items-center space-x-2">
                                <Filter className="h-4 w-4" />
                                <span className="font-medium">Filters</span>
                            </div>
                            <div className="flex flex-col space-y-2 sm:flex-row sm:space-x-2 sm:space-y-0">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        placeholder="Search products..."
                                        value={searchTerm}
                                        onChange={(e) => handleSearch(e.target.value)}
                                        className="pl-9 w-full sm:w-64"
                                    />
                                </div>
                                <Select value={selectedCategory} onValueChange={(value) => {
                                    setSelectedCategory(value);
                                    updateFilters({ category: value });
                                }}>
                                    <SelectTrigger className="w-full sm:w-40">
                                        <SelectValue placeholder="Category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Categories</SelectItem>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={category.slug}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <Select value={selectedStatus} onValueChange={(value) => {
                                    setSelectedStatus(value);
                                    updateFilters({ status: value });
                                }}>
                                    <SelectTrigger className="w-full sm:w-32">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Status</SelectItem>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select value={selectedStockFilter} onValueChange={(value) => {
                                    setSelectedStockFilter(value);
                                    updateFilters({ stock_filter: value });
                                }}>
                                    <SelectTrigger className="w-full sm:w-32">
                                        <SelectValue placeholder="Stock" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Stock</SelectItem>
                                        <SelectItem value="in-stock">In Stock</SelectItem>
                                        <SelectItem value="low-stock">Low Stock</SelectItem>
                                        <SelectItem value="out-of-stock">Out of Stock</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </CardHeader>
                </Card>

                {/* Products Table */}
                <DataTable
                    data={products.data}
                    columns={columns}
                    pagination={products}
                    onPageChange={handlePageChange}
                    searchValue={searchTerm}
                    onSearchChange={handleSearch}
                    searchPlaceholder="Search products..."
                    emptyMessage="No products found"
                />
            </div>
        </AdminLayout>
    );
}
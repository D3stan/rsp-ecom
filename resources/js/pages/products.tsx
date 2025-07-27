import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import Header from '@/components/header';
import ProductCard from '@/components/ProductCard';
import { useState, useEffect, useCallback } from 'react';
import { 
    Search, 
    Filter,
    Grid3X3,
    List,
    ChevronLeft,
    ChevronRight,
    X,
    Star
} from 'lucide-react';

export interface Product {
    id: number;
    name: string;
    slug: string;
    price: number;
    originalPrice?: number;
    rating: number;
    reviews: number;
    image: string;
    badge?: string;
    inStock: boolean;
    stockQuantity: number;
    category?: {
        id: number;
        name: string;
        slug: string;
    };
}

interface Category {
    id: number;
    name: string;
    slug: string;
    count: number;
}

interface PaginationData {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
}

interface ProductsPageProps extends SharedData {
    products: {
        data: Product[];
    };
    categories: Category[];
    priceRange: {
        min: number;
        max: number;
    };
    filters: {
        search: string;
        category: string;
        min_price: string;
        max_price: string;
        sort: string;
        direction: string;
        per_page: number;
    };
    pagination: PaginationData;
}

// Predefined price ranges
const PRICE_RANGES = [
    { label: 'All Prices', min: null, max: null },
    { label: 'Under $25', min: 0, max: 25 },
    { label: '$25 - $50', min: 25, max: 50 },
    { label: '$50 - $100', min: 50, max: 100 },
    { label: '$100 - $200', min: 100, max: 200 },
    { label: '$200 - $500', min: 200, max: 500 },
    { label: 'Over $500', min: 500, max: null },
];

const renderStars = (rating: number) => {
    return Array.from({ length: 5 }, (_, i) => (
        <Star 
            key={i} 
            className={`w-4 h-4 ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} 
        />
    ));
};

export default function Products() {
    const { products, categories, priceRange, filters, pagination } = usePage<ProductsPageProps>().props;
    
    // Local state for filters
    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [selectedCategory, setSelectedCategory] = useState(filters.category || 'all');
    const [priceMin, setPriceMin] = useState(filters.min_price ? parseInt(filters.min_price) : priceRange.min);
    const [priceMax, setPriceMax] = useState(filters.max_price ? parseInt(filters.max_price) : priceRange.max);
    const [sortBy, setSortBy] = useState(filters.sort);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [isFilterOpen, setIsFilterOpen] = useState(false);

    const applyFilters = useCallback((newFilters: Record<string, string | number> = {}) => {
        const params: Record<string, string | number> = {
            search: searchTerm,
            category: selectedCategory === 'all' ? '' : selectedCategory,
            min_price: priceMin !== priceRange.min ? priceMin.toString() : '',
            max_price: priceMax !== priceRange.max ? priceMax.toString() : '',
            sort: sortBy,
            per_page: filters.per_page,
            ...newFilters
        };

        // Remove empty parameters
        Object.keys(params).forEach(key => {
            const value = params[key];
            if (value === '' || value === null || value === undefined) {
                delete params[key];
            }
        });

        router.get(route('products'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    }, [searchTerm, selectedCategory, priceMin, priceMax, sortBy, filters.per_page, priceRange.min, priceRange.max]);

    const applyPriceFilter = () => {
        const params: Record<string, string | number> = {
            search: searchTerm,
            category: selectedCategory === 'all' ? '' : selectedCategory,
            min_price: priceMin !== priceRange.min ? priceMin.toString() : '',
            max_price: priceMax !== priceRange.max ? priceMax.toString() : '',
            sort: sortBy,
            per_page: filters.per_page,
        };

        // Remove empty parameters
        Object.keys(params).forEach(key => {
            const value = params[key];
            if (value === '' || value === null || value === undefined) {
                delete params[key];
            }
        });

        router.get(route('products'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    // Debounced search
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchTerm !== filters.search) {
                applyFilters();
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, filters.search, applyFilters]);

    // Apply filters when category changes
    useEffect(() => {
        if (selectedCategory !== (filters.category || 'all')) {
            applyFilters();
        }
    }, [selectedCategory, filters.category, applyFilters]);

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedCategory('all');
        setPriceMin(priceRange.min);
        setPriceMax(priceRange.max);
        setSortBy('name');
        
        router.get(route('products'), {}, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handlePageChange = (page: number) => {
        router.get(route('products'), {
            ...Object.fromEntries(new URLSearchParams(window.location.search)),
            page: page.toString()
        }, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Products" />
            
            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <Header />
                
                {/* Main Content */}
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
                    {/* Page Header */}
                    <div className="mb-10">
                        <h1 className="text-4xl font-bold text-gray-900 mb-6">All Products</h1>
                        
                        {/* Search Bar */}
                        <div className="flex flex-col lg:flex-row gap-4 items-start lg:items-center justify-between">
                            <div className="flex-1 max-w-2xl">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-5 h-5" />
                                    <Input 
                                        type="search"
                                        placeholder="Search products..."
                                        className="pl-12 pr-4 h-12 text-base bg-white text-gray-900 placeholder-gray-500 border-gray-300 focus:border-black focus:ring-black"
                                        value={searchTerm}
                                        onChange={(e) => setSearchTerm(e.target.value)}
                                    />
                                </div>
                            </div>
                            
                            {/* Controls */}
                            <div className="flex items-center space-x-4">
                                {/* Mobile Filter Button */}
                                <Sheet open={isFilterOpen} onOpenChange={setIsFilterOpen}>
                                    <SheetTrigger asChild>
                                        <Button variant="outline" size="lg" className="lg:hidden">
                                            <Filter className="w-4 h-4 mr-2" />
                                            Filters
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="left" className="w-80">
                                        <SheetHeader>
                                            <SheetTitle>Filters</SheetTitle>
                                        </SheetHeader>
                                        <div className="py-6">
                                            <FilterSection 
                                                categories={categories}
                                                priceRange={priceRange}
                                                selectedCategory={selectedCategory}
                                                setSelectedCategory={setSelectedCategory}
                                                priceMin={priceMin}
                                                setPriceMin={setPriceMin}
                                                priceMax={priceMax}
                                                setPriceMax={setPriceMax}
                                                applyFilters={applyFilters}
                                                clearFilters={clearFilters}
                                            />
                                        </div>
                                    </SheetContent>
                                </Sheet>

                                {/* Sort Dropdown */}
                                <Select value={sortBy} onValueChange={(value) => {
                                    setSortBy(value);
                                    applyFilters({ sort: value });
                                }}>
                                    <SelectTrigger className="w-48 bg-white border-gray-300 text-gray-900">
                                        <SelectValue placeholder="Sort by..." className="text-gray-900" />
                                    </SelectTrigger>
                                    <SelectContent className="bg-white border border-gray-200">
                                        <SelectItem value="name" className="text-gray-900 hover:bg-gray-100">Name (A-Z)</SelectItem>
                                        <SelectItem value="price" className="text-gray-900 hover:bg-gray-100">Price (Low-High)</SelectItem>
                                        <SelectItem value="newest" className="text-gray-900 hover:bg-gray-100">Newest</SelectItem>
                                        <SelectItem value="rating" className="text-gray-900 hover:bg-gray-100">Best Rated</SelectItem>
                                        <SelectItem value="popular" className="text-gray-900 hover:bg-gray-100">Most Popular</SelectItem>
                                        <SelectItem value="most_bought" className="text-gray-900 hover:bg-gray-100">Most Bought</SelectItem>
                                    </SelectContent>
                                </Select>

                                {/* View Mode Toggle */}
                                <div className="hidden md:flex border border-gray-300 rounded-md">
                                    <Button 
                                        variant={viewMode === 'grid' ? 'default' : 'ghost'} 
                                        size="sm" 
                                        className={`rounded-r-none ${viewMode === 'grid' ? 'bg-black text-white hover:bg-gray-900' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'}`}
                                        onClick={() => setViewMode('grid')}
                                    >
                                        <Grid3X3 className="w-4 h-4" />
                                    </Button>
                                    <Button 
                                        variant={viewMode === 'list' ? 'default' : 'ghost'} 
                                        size="sm" 
                                        className={`rounded-l-none ${viewMode === 'list' ? 'bg-black text-white hover:bg-gray-900' : 'text-gray-700 hover:text-gray-900 hover:bg-gray-100'}`}
                                        onClick={() => setViewMode('list')}
                                    >
                                        <List className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-8">
                        {/* Desktop Filters Sidebar */}
                        <div className="hidden lg:block w-72 flex-shrink-0">
                            <div className="bg-white rounded-xl shadow-sm border border-gray-200 p-6 sticky top-6">
                                <FilterSection 
                                    categories={categories}
                                    priceRange={priceRange}
                                    selectedCategory={selectedCategory}
                                    setSelectedCategory={setSelectedCategory}
                                    priceMin={priceMin}
                                    setPriceMin={setPriceMin}
                                    priceMax={priceMax}
                                    setPriceMax={setPriceMax}
                                    applyFilters={applyFilters}
                                    clearFilters={clearFilters}
                                />
                            </div>
                        </div>

                        {/* Products Grid */}
                        <div className="flex-1">
                            {/* Results Summary */}
                            <div className="flex items-center justify-between mb-8">
                                <p className="text-lg font-medium text-gray-700">
                                    Showing {pagination.from || 0}-{pagination.to || 0} of {pagination.total} products
                                </p>
                            </div>

                            {/* Products Grid/List */}
                            {products.data.length > 0 ? (
                                <>
                                    <div className={`grid gap-8 ${
                                        viewMode === 'grid' 
                                            ? 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3' 
                                            : 'grid-cols-1'
                                    }`}>
                                        {products.data.map((product: Product) => (
                                            <ProductCard 
                                                key={product.id} 
                                                product={product} 
                                                viewMode={viewMode}
                                            />
                                        ))}
                                    </div>

                                    {/* Pagination */}
                                    {pagination.last_page > 1 && (
                                        <div className="mt-12">
                                            {/* Mobile Pagination */}
                                            <div className="flex justify-between items-center sm:hidden">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handlePageChange(pagination.current_page - 1)}
                                                    disabled={pagination.current_page <= 1}
                                                >
                                                    <ChevronLeft className="w-4 h-4 mr-1" />
                                                    Prev
                                                </Button>
                                                
                                                <span className="text-sm text-gray-600">
                                                    Page {pagination.current_page} of {pagination.last_page}
                                                </span>
                                                
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handlePageChange(pagination.current_page + 1)}
                                                    disabled={pagination.current_page >= pagination.last_page}
                                                >
                                                    Next
                                                    <ChevronRight className="w-4 h-4 ml-1" />
                                                </Button>
                                            </div>

                                            {/* Desktop Pagination */}
                                            <div className="hidden sm:flex justify-center">
                                                <div className="flex items-center space-x-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handlePageChange(pagination.current_page - 1)}
                                                        disabled={pagination.current_page <= 1}
                                                    >
                                                        <ChevronLeft className="w-4 h-4 mr-1" />
                                                        Previous
                                                    </Button>
                                                    
                                                    {/* Dynamic Page Numbers with ellipsis */}
                                                    {(() => {
                                                        const current = pagination.current_page;
                                                        const total = pagination.last_page;
                                                        const pages = [];
                                                        
                                                        // Always show first page
                                                        if (current > 3) {
                                                            pages.push(
                                                                <Button
                                                                    key={1}
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => handlePageChange(1)}
                                                                >
                                                                    1
                                                                </Button>
                                                            );
                                                            if (current > 4) {
                                                                pages.push(<span key="ellipsis1" className="px-2 text-gray-500">...</span>);
                                                            }
                                                        }
                                                        
                                                        // Show pages around current
                                                        for (let i = Math.max(1, current - 2); i <= Math.min(total, current + 2); i++) {
                                                            pages.push(
                                                                <Button
                                                                    key={i}
                                                                    variant={i === current ? 'default' : 'outline'}
                                                                    size="sm"
                                                                    onClick={() => handlePageChange(i)}
                                                                >
                                                                    {i}
                                                                </Button>
                                                            );
                                                        }
                                                        
                                                        // Always show last page
                                                        if (current < total - 2) {
                                                            if (current < total - 3) {
                                                                pages.push(<span key="ellipsis2" className="px-2 text-gray-500">...</span>);
                                                            }
                                                            pages.push(
                                                                <Button
                                                                    key={total}
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => handlePageChange(total)}
                                                                >
                                                                    {total}
                                                                </Button>
                                                            );
                                                        }
                                                        
                                                        return pages;
                                                    })()}
                                                    
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handlePageChange(pagination.current_page + 1)}
                                                        disabled={pagination.current_page >= pagination.last_page}
                                                    >
                                                        Next
                                                        <ChevronRight className="w-4 h-4 ml-1" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="text-center py-24 bg-white rounded-xl shadow-sm border border-gray-200">
                                    <div className="max-w-md mx-auto">
                                        <h2 className="text-2xl font-bold text-gray-900 mb-3">No products found</h2>
                                        <p className="text-gray-600 mb-6 text-lg">Try adjusting your search or filters to find what you're looking for</p>
                                        <Button onClick={clearFilters} size="lg" className="bg-black hover:bg-gray-900 font-semibold">
                                            Clear all filters
                                        </Button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

// Filter Section Component
interface FilterSectionProps {
    categories: Category[];
    priceRange: { min: number; max: number };
    selectedCategory: string;
    setSelectedCategory: (category: string) => void;
    priceMin: number;
    setPriceMin: (price: number) => void;
    priceMax: number;
    setPriceMax: (price: number) => void;
    applyFilters: () => void;
    clearFilters: () => void;
}

function FilterSection({
    categories,
    priceRange,
    selectedCategory,
    setSelectedCategory,
    priceMin,
    setPriceMin,
    priceMax,
    setPriceMax,
    applyFilters,
    clearFilters
}: FilterSectionProps) {
    return (
        <div className="space-y-8">
            <div className="flex items-center justify-between">
                <h3 className="text-xl font-bold text-gray-900">Filters</h3>
                <Button variant="ghost" size="sm" onClick={clearFilters} className="text-gray-600 hover:text-gray-900">
                    <X className="w-4 h-4 mr-1" />
                    Clear
                </Button>
            </div>

            {/* Categories */}
            <div>
                <Label className="text-base font-semibold mb-4 block text-gray-900">Category</Label>
                <Select value={selectedCategory} onValueChange={(value) => {
                    setSelectedCategory(value);
                    // Category filter will be applied by the useEffect that watches selectedCategory
                }}>
                    <SelectTrigger className="bg-white border-gray-300 text-gray-900">
                        <SelectValue placeholder="All Categories" className="text-gray-900" />
                    </SelectTrigger>
                    <SelectContent className="bg-white border border-gray-200">
                        <SelectItem value="all" className="text-gray-900 hover:bg-gray-100">All Categories</SelectItem>
                        {categories.map((category) => (
                            <SelectItem key={category.id} value={category.slug} className="text-gray-900 hover:bg-gray-100">
                                {category.name} ({category.count})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* Price Range */}
            <div>
                <Label className="text-base font-semibold mb-4 block text-gray-900">Price Range</Label>
                <div className="space-y-3">
                    {PRICE_RANGES.map((range, index) => (
                        <div key={index} className="flex items-center space-x-3">
                            <input
                                type="radio"
                                id={`price-range-${index}`}
                                name="price-range"
                                checked={
                                    (range.min === null ? priceRange.min : range.min) === priceMin &&
                                    (range.max === null ? priceRange.max : range.max) === priceMax
                                }
                                onChange={() => {
                                    const newMin = range.min === null ? priceRange.min : range.min;
                                    const newMax = range.max === null ? priceRange.max : range.max;
                                    setPriceMin(newMin);
                                    setPriceMax(newMax);
                                }}
                                className="w-4 h-4 text-black border-gray-300 focus:ring-black focus:ring-2"
                            />
                            <Label htmlFor={`price-range-${index}`} className="text-sm cursor-pointer text-gray-700 font-medium">
                                {range.label}
                            </Label>
                        </div>
                    ))}
                </div>
                
                {/* Custom Range Inputs */}
                <div className="mt-6 space-y-4 pt-4 border-t border-gray-200">
                    <Label className="text-sm font-semibold text-gray-900">Custom Range</Label>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label className="text-xs text-gray-600 font-medium">Min</Label>
                            <Input 
                                type="number" 
                                value={priceMin}
                                onChange={(e) => setPriceMin(parseInt(e.target.value) || priceRange.min)}
                                min={priceRange.min}
                                max={priceRange.max}
                                className="h-10 border-gray-300 focus:border-black focus:ring-black"
                            />
                        </div>
                        <div>
                            <Label className="text-xs text-gray-600 font-medium">Max</Label>
                            <Input 
                                type="number" 
                                value={priceMax}
                                onChange={(e) => setPriceMax(parseInt(e.target.value) || priceRange.max)}
                                min={priceRange.min}
                                max={priceRange.max}
                                className="h-10 border-gray-300 focus:border-black focus:ring-black"
                            />
                        </div>
                    </div>
                    <div className="text-sm text-gray-700 text-center font-medium bg-gray-50 py-2 px-3 rounded-md">
                        ${priceMin} - ${priceMax}
                    </div>
                </div>
            </div>

            <Button onClick={applyFilters} className="w-full h-12 font-semibold text-base text-white bg-black hover:bg-gray-900">
                Apply Filters
            </Button>
        </div>
    );
}

// Quick View Modal Component
interface QuickViewModalProps {
    product: Product;
    isOpen: boolean;
    onClose: () => void;
    onAddToCart: (quantity: number) => void;
    onNavigateToProduct: () => void;
}

function QuickViewModal({ product, isOpen, onClose, onAddToCart, onNavigateToProduct }: QuickViewModalProps) {
    const [quantity, setQuantity] = useState(1);
    const [imageSrc, setImageSrc] = useState(product.image);

    const decreaseQuantity = () => {
        if (quantity > 1) setQuantity(quantity - 1);
    };

    const increaseQuantity = () => {
        if (quantity < product.stockQuantity) setQuantity(quantity + 1);
    };

    const handleImageError = () => {
        setImageSrc('/images/product.png');
    };

    return (
        <Dialog open={isOpen} onOpenChange={onClose}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto bg-white" aria-describedby="quick-view-description">
                <DialogHeader>
                    <DialogTitle className="text-gray-900">Quick View</DialogTitle>
                </DialogHeader>
                <div id="quick-view-description" className="sr-only">
                    Quick view modal for {product.name}. View product details, select quantity, and add to cart.
                </div>
                
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    {/* Product Image */}
                    <div className="aspect-square overflow-hidden rounded-lg bg-gray-100">
                        <img 
                            src={imageSrc} 
                            alt={product.name}
                            onError={handleImageError}
                            className="w-full h-full object-cover"
                        />
                    </div>
                    
                    {/* Product Details */}
                    <div className="space-y-4">
                        {product.badge && (
                            <Badge 
                                variant={product.badge === 'Sale' ? 'destructive' : 'secondary'}
                                className="w-fit"
                            >
                                {product.badge}
                            </Badge>
                        )}
                        
                        <div>
                            <h2 className="text-2xl font-bold text-gray-900">{product.name}</h2>
                            {product.category && (
                                <p className="text-sm text-gray-600 mt-1">{product.category.name}</p>
                            )}
                        </div>
                        
                        {/* Rating */}
                        <div className="flex items-center space-x-2">
                            <div className="flex">
                                {renderStars(product.rating)}
                            </div>
                            <span className="text-sm text-gray-600">
                                {product.rating} ({product.reviews} reviews)
                            </span>
                        </div>
                        
                        {/* Price */}
                        <div className="flex items-center space-x-3">
                            <span className="text-3xl font-bold text-black">${product.price}</span>
                            {product.originalPrice && (
                                <span className="text-xl text-gray-500 line-through">
                                    ${product.originalPrice}
                                </span>
                            )}
                        </div>
                        
                        {/* Stock Status */}
                        <div className="flex items-center space-x-2">
                            <div className={`w-2 h-2 rounded-full ${product.inStock ? 'bg-green-500' : 'bg-red-500'}`} />
                            <span className="text-sm text-gray-600">
                                {product.inStock ? `${product.stockQuantity} in stock` : 'Out of stock'}
                            </span>
                        </div>
                        
                        {/* Quantity Selector */}
                        {product.inStock && (
                            <div className="space-y-3">
                                <Label className="text-sm font-medium">Quantity</Label>
                                <div className="flex items-center space-x-3">
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={decreaseQuantity}
                                        disabled={quantity <= 1}
                                        className="h-8 w-8"
                                    >
                                        <Minus className="w-4 h-4" />
                                    </Button>
                                    <span className="w-12 text-center font-medium">{quantity}</span>
                                    <Button
                                        variant="outline"
                                        size="icon"
                                        onClick={increaseQuantity}
                                        disabled={quantity >= product.stockQuantity}
                                        className="h-8 w-8"
                                    >
                                        <Plus className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>
                        )}
                        
                        {/* Action Buttons */}
                        <div className="space-y-3 pt-4">
                            <Button 
                                className="w-full" 
                                disabled={!product.inStock}
                                onClick={() => onAddToCart(quantity)}
                            >
                                <ShoppingCart className="w-4 h-4 mr-2" />
                                {product.inStock ? 'Add to Cart' : 'Out of Stock'}
                            </Button>
                            
                            <Button 
                                variant="outline" 
                                className="w-full"
                                onClick={onNavigateToProduct}
                            >
                                View Full Details
                            </Button>
                            
                            <Button variant="outline" size="sm" className="w-full">
                                <Heart className="w-4 h-4 mr-2" />
                                Add to Wishlist
                            </Button>
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

// Product Card Component
interface ProductCardProps {
    product: Product;
    viewMode: 'grid' | 'list';
}

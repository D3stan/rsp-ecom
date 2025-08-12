import Header from '@/components/header';
import ProductCard from '@/components/ProductCard';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/useTranslation';
import { type SharedData } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Filter, Grid3X3, List, Search, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

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

export default function Products() {
    const { products, categories, priceRange, filters, pagination } = usePage<ProductsPageProps>().props;
    const { t } = useTranslation();

    // Predefined price ranges with translations
    const PRICE_RANGES = [
        { label: t('products.price_ranges.all_prices'), min: null, max: null },
        { label: t('products.price_ranges.under_25'), min: 0, max: 25 },
        { label: t('products.price_ranges.25_50'), min: 25, max: 50 },
        { label: t('products.price_ranges.50_100'), min: 50, max: 100 },
        { label: t('products.price_ranges.100_200'), min: 100, max: 200 },
        { label: t('products.price_ranges.200_500'), min: 200, max: 500 },
        { label: t('products.price_ranges.over_500'), min: 500, max: null },
    ];

    // Local state for filters
    const [searchTerm, setSearchTerm] = useState(filters.search);
    const [selectedCategory, setSelectedCategory] = useState(filters.category || 'all');
    const [priceMin, setPriceMin] = useState(filters.min_price ? parseInt(filters.min_price) : priceRange.min);
    const [priceMax, setPriceMax] = useState(filters.max_price ? parseInt(filters.max_price) : priceRange.max);
    const [sortBy, setSortBy] = useState(filters.sort);
    const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
    const [isFilterOpen, setIsFilterOpen] = useState(false);

    const applyFilters = useCallback(
        (overrides: Record<string, string | number> = {}) => {
            const params: Record<string, string | number> = {
                search: searchTerm,
                category: selectedCategory === 'all' ? '' : selectedCategory,
                min_price: priceMin !== priceRange?.min ? priceMin.toString() : '',
                max_price: priceMax !== priceRange?.max ? priceMax.toString() : '',
                sort: sortBy,
                per_page: filters.per_page,
                ...overrides,
            };

            // Remove empty parameters
            Object.keys(params).forEach((key) => {
                const value = params[key];
                if (value === '' || value === null || value === undefined) {
                    delete params[key];
                }
            });

            router.get(route('products'), params, {
                preserveState: true,
                preserveScroll: true,
            });
        },
        [searchTerm, selectedCategory, priceMin, priceMax, sortBy, filters.per_page, priceRange?.min, priceRange?.max],
    );

    // Only debounce search, don't auto-apply other filters
    useEffect(() => {
        const timer = setTimeout(() => {
            if (searchTerm !== filters.search) {
                // Inline the filter logic to avoid circular dependency
                const params: Record<string, string | number> = {
                    search: searchTerm,
                    category: selectedCategory === 'all' ? '' : selectedCategory,
                    min_price: priceMin !== priceRange?.min ? priceMin.toString() : '',
                    max_price: priceMax !== priceRange?.max ? priceMax.toString() : '',
                    sort: sortBy,
                    per_page: filters.per_page,
                };

                // Remove empty parameters
                Object.keys(params).forEach((key) => {
                    const value = params[key];
                    if (value === '' || value === null || value === undefined) {
                        delete params[key];
                    }
                });

                router.get(route('products'), params, {
                    preserveState: true,
                    preserveScroll: true,
                });
            }
        }, 500);
        return () => clearTimeout(timer);
    }, [searchTerm, filters.search, selectedCategory, priceMin, priceMax, sortBy, filters.per_page, priceRange?.min, priceRange?.max]);

    const handleApplyFilters = () => {
        const params: Record<string, string | number> = {
            search: searchTerm,
            category: selectedCategory === 'all' ? '' : selectedCategory,
            min_price: priceMin !== priceRange?.min ? priceMin.toString() : '',
            max_price: priceMax !== priceRange?.max ? priceMax.toString() : '',
            sort: sortBy,
            per_page: filters.per_page,
        };

        // Remove empty parameters
        Object.keys(params).forEach((key) => {
            const value = params[key];
            if (value === '' || value === null || value === undefined) {
                delete params[key];
            }
        });

        // Close the mobile filter modal
        setIsFilterOpen(false);

        router.get(route('products'), params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearFilters = () => {
        setSearchTerm('');
        setSelectedCategory('all');
        setPriceMin(priceRange?.min || 0);
        setPriceMax(priceRange?.max || 1000);
        setSortBy('name');

        router.get(
            route('products'),
            {},
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    };

    const handlePageChange = (page: number) => {
        router.get(
            route('products'),
            {
                ...Object.fromEntries(new URLSearchParams(window.location.search)),
                page: page.toString(),
            },
            {
                preserveState: true,
                preserveScroll: true,
                onSuccess: () => {
                    // Scroll to the products section after page change
                    const productsSection = document.querySelector('[data-products-section]');
                    if (productsSection) {
                        productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    } else {
                        // Fallback: scroll to top
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                    }
                },
            },
        );
    };

    return (
        <>
            <Head title={t('products.title')} />

            <div className="min-h-screen bg-gray-50">
                {/* Header */}
                <Header />

                {/* Main Content */}
                <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                    {/* Page Header */}
                    <div className="mb-10">
                        <h1 className="mb-6 text-4xl font-bold text-gray-900">{t('products.all_products')}</h1>

                        {/* Search Bar */}
                        <div className="flex flex-col items-start justify-between gap-4 lg:flex-row lg:items-center">
                            <div className="max-w-2xl flex-1">
                                <div className="relative">
                                    <Search className="absolute top-1/2 left-3 h-5 w-5 -translate-y-1/2 transform text-gray-400" />
                                    <Input
                                        type="search"
                                        placeholder={t('products.search_placeholder')}
                                        className="h-12 border-gray-300 bg-white pr-4 pl-12 text-base text-gray-900 placeholder-gray-500 focus:border-black focus:ring-black"
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
                                            <Filter className="mr-2 h-4 w-4" />
                                            {t('products.filters')}
                                        </Button>
                                    </SheetTrigger>
                                    <SheetContent side="left" className="filters-modal-content w-80 border-r border-gray-200 bg-white">
                                        <SheetHeader className="mb-6 border-b border-gray-200 pb-4">
                                            <SheetTitle className="text-left text-lg font-semibold text-gray-900">{t('products.filters')}</SheetTitle>
                                        </SheetHeader>
                                        <div className="px-4 py-0">
                                            <FilterSection
                                                categories={categories}
                                                priceRange={priceRange}
                                                selectedCategory={selectedCategory}
                                                setSelectedCategory={setSelectedCategory}
                                                priceMin={priceMin}
                                                setPriceMin={setPriceMin}
                                                priceMax={priceMax}
                                                setPriceMax={setPriceMax}
                                                applyFilters={handleApplyFilters}
                                                clearFilters={clearFilters}
                                                closeModal={() => setIsFilterOpen(false)}
                                                priceRanges={PRICE_RANGES}
                                            />
                                        </div>
                                    </SheetContent>
                                </Sheet>

                                {/* Sort Dropdown */}
                                <Select
                                    value={sortBy}
                                    onValueChange={(value) => {
                                        setSortBy(value);
                                        applyFilters({ sort: value });
                                    }}
                                >
                                    <SelectTrigger className="w-48 border-gray-300 bg-white text-gray-900">
                                        <SelectValue placeholder={t('products.sort_by_placeholder')} className="text-gray-900" />
                                    </SelectTrigger>
                                    <SelectContent className="border border-gray-200 bg-white">
                                        <SelectItem value="name" className="text-gray-900 hover:bg-gray-100">
                                            {t('products.sort_name_az')}
                                        </SelectItem>
                                        <SelectItem value="price" className="text-gray-900 hover:bg-gray-100">
                                            {t('products.sort_price_low_high')}
                                        </SelectItem>
                                        <SelectItem value="newest" className="text-gray-900 hover:bg-gray-100">
                                            {t('products.sort_newest')}
                                        </SelectItem>
                                        <SelectItem value="rating" className="text-gray-900 hover:bg-gray-100">
                                            {t('products.sort_rating')}
                                        </SelectItem>
                                        <SelectItem value="popular" className="text-gray-900 hover:bg-gray-100">
                                            {t('products.sort_popular')}
                                        </SelectItem>
                                        <SelectItem value="most_bought" className="text-gray-900 hover:bg-gray-100">
                                            {t('products.sort_most_bought')}
                                        </SelectItem>
                                    </SelectContent>
                                </Select>

                                {/* View Mode Toggle */}
                                <div className="hidden rounded-md border border-gray-300 md:flex">
                                    <Button
                                        variant={viewMode === 'grid' ? 'default' : 'ghost'}
                                        size="sm"
                                        className={`rounded-r-none ${viewMode === 'grid' ? 'bg-black text-white hover:bg-gray-900' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'}`}
                                        onClick={() => setViewMode('grid')}
                                    >
                                        <Grid3X3 className="h-4 w-4" />
                                    </Button>
                                    <Button
                                        variant={viewMode === 'list' ? 'default' : 'ghost'}
                                        size="sm"
                                        className={`rounded-l-none ${viewMode === 'list' ? 'bg-black text-white hover:bg-gray-900' : 'text-gray-700 hover:bg-gray-100 hover:text-gray-900'}`}
                                        onClick={() => setViewMode('list')}
                                    >
                                        <List className="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="flex gap-8">
                        {/* Desktop Filters Sidebar */}
                        <div className="hidden w-72 flex-shrink-0 lg:block">
                            <div className="filters-modal-content sticky top-6 rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
                                <FilterSection
                                    categories={categories}
                                    priceRange={priceRange}
                                    selectedCategory={selectedCategory}
                                    setSelectedCategory={setSelectedCategory}
                                    priceMin={priceMin}
                                    setPriceMin={setPriceMin}
                                    priceMax={priceMax}
                                    setPriceMax={setPriceMax}
                                    applyFilters={handleApplyFilters}
                                    clearFilters={clearFilters}
                                    priceRanges={PRICE_RANGES}
                                />
                            </div>
                        </div>

                        {/* Products Grid */}
                        <div className="flex-1" data-products-section>
                            {/* Results Summary */}
                            <div className="mb-8 flex items-center justify-between">
                                <p className="text-lg font-medium text-gray-700">
                                    {t('products.showing_results', {
                                        from: pagination.from || 0,
                                        to: pagination.to || 0,
                                        total: pagination.total,
                                    })}
                                </p>
                            </div>

                            {/* Products Grid/List */}
                            {products.data.length > 0 ? (
                                <>
                                    <div
                                        className={`grid gap-8 ${viewMode === 'grid' ? 'grid-cols-2 sm:grid-cols-2 lg:grid-cols-3' : 'grid-cols-1'}`}
                                    >
                                        {products.data.map((product: Product) => (
                                            <ProductCard key={product.id} product={product} viewMode={viewMode} />
                                        ))}
                                    </div>

                                    {/* Pagination */}
                                    {pagination.last_page > 1 && (
                                        <div className="mt-12">
                                            {/* Mobile Pagination */}
                                            <div className="flex items-center justify-between sm:hidden">
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handlePageChange(pagination.current_page - 1)}
                                                    disabled={pagination.current_page <= 1}
                                                >
                                                    <ChevronLeft className="mr-1 h-4 w-4" />
                                                    {t('products.prev')}
                                                </Button>

                                                <span className="text-sm text-gray-600">
                                                    {t('products.page_of', { current: pagination.current_page, total: pagination.last_page })}
                                                </span>

                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() => handlePageChange(pagination.current_page + 1)}
                                                    disabled={pagination.current_page >= pagination.last_page}
                                                >
                                                    {t('products.next')}
                                                    <ChevronRight className="ml-1 h-4 w-4" />
                                                </Button>
                                            </div>

                                            {/* Desktop Pagination */}
                                            <div className="hidden justify-center sm:flex">
                                                <div className="flex items-center space-x-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() => handlePageChange(pagination.current_page - 1)}
                                                        disabled={pagination.current_page <= 1}
                                                    >
                                                        <ChevronLeft className="mr-1 h-4 w-4" />
                                                        {t('products.previous')}
                                                    </Button>

                                                    {/* Dynamic Page Numbers with ellipsis */}
                                                    {(() => {
                                                        const current = pagination.current_page;
                                                        const total = pagination.last_page;
                                                        const pages = [];

                                                        // Always show first page
                                                        if (current > 3) {
                                                            pages.push(
                                                                <Button key={1} variant="outline" size="sm" onClick={() => handlePageChange(1)}>
                                                                    1
                                                                </Button>,
                                                            );
                                                            if (current > 4) {
                                                                pages.push(
                                                                    <span key="ellipsis1" className="px-2 text-gray-500">
                                                                        ...
                                                                    </span>,
                                                                );
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
                                                                </Button>,
                                                            );
                                                        }

                                                        // Always show last page
                                                        if (current < total - 2) {
                                                            if (current < total - 3) {
                                                                pages.push(
                                                                    <span key="ellipsis2" className="px-2 text-gray-500">
                                                                        ...
                                                                    </span>,
                                                                );
                                                            }
                                                            pages.push(
                                                                <Button
                                                                    key={total}
                                                                    variant="outline"
                                                                    size="sm"
                                                                    onClick={() => handlePageChange(total)}
                                                                >
                                                                    {total}
                                                                </Button>,
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
                                                        {t('products.next')}
                                                        <ChevronRight className="ml-1 h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <div className="rounded-xl border border-gray-200 bg-white py-24 text-center shadow-sm">
                                    <div className="mx-auto max-w-md">
                                        <h2 className="mb-3 text-2xl font-bold text-gray-900">{t('products.no_products')}</h2>
                                        <p className="mb-6 text-lg text-gray-600">{t('products.try_adjusting_filters')}</p>
                                        <Button onClick={clearFilters} size="lg" className="bg-black font-semibold text-white hover:bg-gray-900">
                                            {t('products.clear_all_filters')}
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
    closeModal?: () => void;
    priceRanges: { label: string; min: number | null; max: number | null }[];
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
    clearFilters,
    closeModal,
    priceRanges,
}: FilterSectionProps) {
    const { t } = useTranslation();

    const handleApplyFilters = () => {
        applyFilters();
        // Close modal only if we're in mobile mode and closeModal is provided
        if (closeModal) {
            closeModal();
        }
    };

    return (
        <div className="space-y-6 bg-white">
            <div className="flex items-center justify-between">
                <h3 className="text-lg font-semibold text-gray-900">{t('products.filters')}</h3>
                <Button variant="ghost" size="sm" onClick={clearFilters} className="px-2 py-1 text-gray-600 hover:bg-gray-100 hover:text-gray-900">
                    <X className="mr-1 h-4 w-4" />
                    {t('products.clear')}
                </Button>
            </div>

            {/* Categories */}
            <div className="space-y-3">
                <Label className="block text-sm font-semibold text-gray-900">{t('products.category')}</Label>
                <Select
                    value={selectedCategory}
                    onValueChange={(value) => {
                        setSelectedCategory(value);
                        applyFilters();
                    }}
                >
                    <SelectTrigger className="h-10 w-full border-gray-300 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500">
                        <SelectValue placeholder={t('products.all_categories')} className="text-gray-900" />
                    </SelectTrigger>
                    <SelectContent className="border border-gray-200 bg-white shadow-lg">
                        <SelectItem
                            value="all"
                            className="text-gray-900 hover:bg-gray-100 focus:bg-gray-100 data-[state=checked]:bg-blue-500 data-[state=checked]:text-white"
                        >
                            {t('products.all_categories')}
                        </SelectItem>
                        {categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={category.slug}
                                className="text-gray-900 hover:bg-gray-100 focus:bg-gray-100 data-[state=checked]:bg-blue-500 data-[state=checked]:text-white"
                            >
                                {category.name} ({category.count})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>

            {/* Price Range */}
            <div className="space-y-4">
                <Label className="block text-sm font-semibold text-gray-900">{t('products.price_range')}</Label>
                <div className="space-y-3">
                    {priceRanges.map((range, index) => (
                        <div key={index} className="flex items-center space-x-3">
                            <input
                                type="radio"
                                id={`price-range-${index}`}
                                name="price-range"
                                checked={
                                    (range.min === null ? priceRange?.min : range.min) === priceMin &&
                                    (range.max === null ? priceRange?.max : range.max) === priceMax
                                }
                                onChange={() => {
                                    const newMin = range.min === null ? priceRange?.min || 0 : range.min;
                                    const newMax = range.max === null ? priceRange?.max || 1000 : range.max;
                                    setPriceMin(newMin);
                                    setPriceMax(newMax);
                                }}
                                className="h-4 w-4 border-gray-300 text-blue-600 focus:ring-2 focus:ring-blue-500"
                            />
                            <Label htmlFor={`price-range-${index}`} className="flex-1 cursor-pointer text-sm font-normal text-gray-700">
                                {range.label}
                            </Label>
                        </div>
                    ))}
                </div>

                {/* Custom Range Inputs */}
                <div className="mt-4 space-y-3 border-t border-gray-200 pt-4">
                    <Label className="block text-sm font-semibold text-gray-900">{t('products.custom_range')}</Label>
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label className="text-xs font-medium text-gray-600">{t('products.min')}</Label>
                            <Input
                                type="number"
                                value={priceMin}
                                onChange={(e) => setPriceMin(parseInt(e.target.value) || priceRange?.min || 0)}
                                min={priceRange?.min || 0}
                                max={priceRange?.max || 1000}
                                className="h-10 border-gray-300 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="0"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs font-medium text-gray-600">{t('products.max')}</Label>
                            <Input
                                type="number"
                                value={priceMax}
                                onChange={(e) => setPriceMax(parseInt(e.target.value) || priceRange?.max || 1000)}
                                min={priceRange?.min || 0}
                                max={priceRange?.max || 1000}
                                className="h-10 border-gray-300 bg-white text-gray-900 focus:border-blue-500 focus:ring-blue-500"
                                placeholder="1000"
                            />
                        </div>
                    </div>
                    <div className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-center text-sm font-medium text-gray-700">
                        ${priceMin.toLocaleString()} - ${priceMax.toLocaleString()}
                    </div>
                </div>
            </div>

            <Button
                onClick={handleApplyFilters}
                className="h-11 w-full bg-blue-600 text-base font-semibold text-white transition-all duration-200 hover:bg-blue-700 focus:ring-4 focus:ring-blue-200"
            >
                {t('products.apply_filters')}
            </Button>
        </div>
    );
}

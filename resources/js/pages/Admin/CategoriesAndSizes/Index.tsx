import { useState, useEffect } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import AdminLayout from '@/layouts/admin-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
    Package, 
    Tag, 
    TrendingUp,
    Plus,
    Edit,
    Trash2,
    Save,
    X,
    GripVertical,
    AlertTriangle,
    BarChart3,
    Ruler,
    Box,
    ChevronDown,
    ChevronUp
} from 'lucide-react';

import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

interface Category {
    id: number;
    name: string;
    slug: string;
    description?: string;
    image?: string;
    is_active: boolean;
    sort_order: number;
    products_count: number;
}

interface Size {
    id: number;
    name: string;
    length: number;
    width: number;
    height: number;
    box_type?: string;
    shipping_cost: number;
    products_count: number;
}

interface CategoryStats {
    total_categories: number;
    active_categories: number;
    products_with_categories: number;
    total_products: number;
}

interface SizeStats {
    total_sizes: number;
    sizes_with_products: number;
    total_products: number;
    avg_shipping_cost: number;
}

interface Props {
    categories: Category[];
    sizes: Size[];
    boxTypes: Record<string, string>;
    categoryStats: CategoryStats;
    sizeStats: SizeStats;
}

export default function CategoriesAndSizesIndex({ categories, sizes, boxTypes, categoryStats, sizeStats }: Props) {
    const [editingCategory, setEditingCategory] = useState<number | null>(null);
    const [editingSize, setEditingSize] = useState<number | null>(null);
    const [isStatsOpen, setIsStatsOpen] = useState(false);
    const [activeTab, setActiveTab] = useState<'categories' | 'sizes'>('categories');

    // Category form
    const categoryForm = useForm({
        name: '',
        description: '',
        image: '',
        is_active: true as boolean,
        sort_order: 0,
    });

    // Size form
    const sizeForm = useForm({
        name: '',
        length: 0,
        width: 0,
        height: 0,
        box_type: '',
        shipping_cost: 0,
    });

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDimensions = (length: number, width: number, height: number) => {
        return `${length} × ${width} × ${height}`;
    };

    const getBoxTypeName = (boxType: string | undefined) => {
        if (!boxType) return 'Not specified';
        return boxTypes[boxType] || boxType;
    };

    const handleCategorySubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (editingCategory) {
            categoryForm.patch(`/admin/categories/${editingCategory}`, {
                onSuccess: () => {
                    setEditingCategory(null);
                    categoryForm.reset();
                },
            });
        } else {
            categoryForm.post('/admin/categories', {
                onSuccess: () => {
                    categoryForm.reset();
                },
            });
        }
    };

    const handleSizeSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        if (editingSize) {
            sizeForm.patch(`/admin/sizes/${editingSize}`, {
                onSuccess: () => {
                    setEditingSize(null);
                    sizeForm.reset();
                },
            });
        } else {
            sizeForm.post('/admin/sizes', {
                onSuccess: () => {
                    sizeForm.reset();
                },
            });
        }
    };

    const startEditCategory = (category: Category) => {
        setEditingCategory(category.id);
        categoryForm.setData('name', category.name);
        categoryForm.setData('description', category.description || '');
        categoryForm.setData('image', category.image || '');
        categoryForm.setData('is_active', Boolean(category.is_active));
        categoryForm.setData('sort_order', category.sort_order);
    };

    const startEditSize = (size: Size) => {
        setEditingSize(size.id);
        sizeForm.setData({
            name: size.name,
            length: size.length,
            width: size.width,
            height: size.height,
            box_type: size.box_type || '',
            shipping_cost: size.shipping_cost,
        });
    };

    const cancelEdit = () => {
        setEditingCategory(null);
        setEditingSize(null);
        categoryForm.reset();
        sizeForm.reset();
    };

    const deleteCategory = (id: number) => {
        if (confirm('Are you sure you want to delete this category?')) {
            router.delete(`/admin/categories/${id}`);
        }
    };

    const deleteSize = (id: number) => {
        if (confirm('Are you sure you want to delete this size?')) {
            router.delete(`/admin/sizes/${id}`);
        }
    };

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Categories & Sizes', href: '/admin/categories-sizes' },
            ]}
        >
            <Head title="Categories & Sizes - Admin" />

            <div className="space-y-4 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Categories & Sizes</h1>
                        <p className="text-sm md:text-base text-muted-foreground">
                            Manage product taxonomy and logistics dimensions
                        </p>
                    </div>
                </div>

                {/* Mobile Tab Switcher */}
                <div className="md:hidden">
                    <div className="flex border rounded-lg p-1 bg-muted">
                        <button
                            onClick={() => setActiveTab('categories')}
                            className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors ${
                                activeTab === 'categories'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Tag className="h-4 w-4 inline mr-2" />
                            Categories
                        </button>
                        <button
                            onClick={() => setActiveTab('sizes')}
                            className={`flex-1 py-2 px-3 rounded-md text-sm font-medium transition-colors ${
                                activeTab === 'sizes'
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Ruler className="h-4 w-4 inline mr-2" />
                            Sizes
                        </button>
                    </div>
                </div>

                {/* Collapsible Stats Cards */}
                <Collapsible open={isStatsOpen} onOpenChange={setIsStatsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button 
                            variant="outline" 
                            className="w-full justify-between"
                            size="sm"
                        >
                            <span className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" />
                                Quick Stats
                            </span>
                            {isStatsOpen ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-4 mt-4">
                        <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Tag className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">Categories</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{categoryStats.total_categories}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {categoryStats.active_categories} active
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Package className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">Categorized</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{categoryStats.products_with_categories}</p>
                                    <p className="text-xs text-muted-foreground">
                                        Products
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Ruler className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">Sizes</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{sizeStats.total_sizes}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {sizeStats.sizes_with_products} with products
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Box className="h-4 w-4 text-orange-600" />
                                    <h3 className="text-xs font-medium">Avg. Shipping</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{formatCurrency(sizeStats.avg_shipping_cost)}</p>
                                    <p className="text-xs text-muted-foreground">
                                        Per package
                                    </p>
                                </div>
                            </Card>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                {/* Desktop: Two Column Layout */}
                <div className="hidden md:grid md:grid-cols-2 gap-6">
                    {/* Categories Section */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xl font-semibold flex items-center gap-2">
                                <Tag className="h-5 w-5" />
                                Categories
                            </h2>
                        </div>

                        {/* Categories List */}
                        <Card className="max-h-96 overflow-y-auto">
                            <div className="space-y-2 p-4">
                                {categories.map((category) => (
                                    <div
                                        key={category.id}
                                        className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50"
                                    >
                                        <div className="flex items-center space-x-3">
                                            <GripVertical className="h-4 w-4 text-muted-foreground" />
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">{category.name}</span>
                                                    {!category.is_active && (
                                                        <Badge variant="secondary" className="bg-gray-100 text-gray-800">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {category.products_count} products
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center space-x-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => startEditCategory(category)}
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => deleteCategory(category.id)}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* Category Form */}
                        <Card className="p-4">
                            <h3 className="text-lg font-medium mb-4">
                                {editingCategory ? 'Edit Category' : 'Add Category'}
                            </h3>
                            <form onSubmit={handleCategorySubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="category-name">Name</Label>
                                    <Input
                                        id="category-name"
                                        value={categoryForm.data.name}
                                        onChange={(e) => categoryForm.setData('name', e.target.value)}
                                        required
                                    />
                                    {categoryForm.errors.name && (
                                        <p className="text-sm text-red-600 mt-1">{categoryForm.errors.name}</p>
                                    )}
                                </div>

                                <div>
                                    <Label htmlFor="category-description">Description</Label>
                                    <Textarea
                                        id="category-description"
                                        value={categoryForm.data.description}
                                        onChange={(e) => categoryForm.setData('description', e.target.value)}
                                        rows={3}
                                    />
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Switch
                                        id="category-active"
                                        checked={categoryForm.data.is_active}
                                        onCheckedChange={(checked) => categoryForm.setData('is_active', Boolean(checked))}
                                    />
                                    <Label htmlFor="category-active">Active</Label>
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={categoryForm.processing}>
                                        <Save className="h-4 w-4 mr-2" />
                                        {editingCategory ? 'Update' : 'Create'}
                                    </Button>
                                    {editingCategory && (
                                        <Button type="button" variant="outline" onClick={cancelEdit}>
                                            <X className="h-4 w-4 mr-2" />
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </Card>
                    </div>

                    {/* Sizes Section */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-xl font-semibold flex items-center gap-2">
                                <Ruler className="h-5 w-5" />
                                Sizes
                            </h2>
                        </div>

                        {/* Sizes List */}
                        <Card className="max-h-96 overflow-y-auto">
                            <div className="space-y-2 p-4">
                                {sizes.map((size) => (
                                    <div
                                        key={size.id}
                                        className="flex items-center justify-between p-3 border rounded-lg hover:bg-muted/50"
                                    >
                                        <div>
                                            <div className="flex items-center gap-2">
                                                <span className="font-medium">{size.name}</span>
                                                <Badge variant="outline" className="text-xs">
                                                    {formatDimensions(size.length, size.width, size.height)}
                                                </Badge>
                                            </div>
                                            <div className="flex items-center gap-4 text-sm text-muted-foreground">
                                                <span>{size.products_count} products</span>
                                                <span>{formatCurrency(size.shipping_cost)} shipping</span>
                                            </div>
                                        </div>
                                        <div className="flex items-center space-x-2">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => startEditSize(size)}
                                            >
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => deleteSize(size.id)}
                                                className="text-red-600 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>

                        {/* Size Form */}
                        <Card className="p-4">
                            <h3 className="text-lg font-medium mb-4">
                                {editingSize ? 'Edit Size' : 'Add Size'}
                            </h3>
                            <form onSubmit={handleSizeSubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="size-name">Name</Label>
                                    <Input
                                        id="size-name"
                                        value={sizeForm.data.name}
                                        onChange={(e) => sizeForm.setData('name', e.target.value)}
                                        required
                                    />
                                    {sizeForm.errors.name && (
                                        <p className="text-sm text-red-600 mt-1">{sizeForm.errors.name}</p>
                                    )}
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div>
                                        <Label htmlFor="size-length">Length (in)</Label>
                                        <Input
                                            id="size-length"
                                            type="number"
                                            step="0.01"
                                            value={sizeForm.data.length}
                                            onChange={(e) => sizeForm.setData('length', parseFloat(e.target.value) || 0)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="size-width">Width (in)</Label>
                                        <Input
                                            id="size-width"
                                            type="number"
                                            step="0.01"
                                            value={sizeForm.data.width}
                                            onChange={(e) => sizeForm.setData('width', parseFloat(e.target.value) || 0)}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="size-height">Height (in)</Label>
                                        <Input
                                            id="size-height"
                                            type="number"
                                            step="0.01"
                                            value={sizeForm.data.height}
                                            onChange={(e) => sizeForm.setData('height', parseFloat(e.target.value) || 0)}
                                            required
                                        />
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="size-box-type">Box Type</Label>
                                    <Select
                                        value={sizeForm.data.box_type || "none"}
                                        onValueChange={(value) => sizeForm.setData('box_type', value === "none" ? "" : value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select box type" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">No box type</SelectItem>
                                            {Object.entries(boxTypes).map(([key, label]) => (
                                                <SelectItem key={key} value={key}>
                                                    {label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div>
                                    <Label htmlFor="size-shipping-cost">Shipping Cost</Label>
                                    <Input
                                        id="size-shipping-cost"
                                        type="number"
                                        step="0.01"
                                        value={sizeForm.data.shipping_cost}
                                        onChange={(e) => sizeForm.setData('shipping_cost', parseFloat(e.target.value) || 0)}
                                        required
                                    />
                                </div>

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={sizeForm.processing}>
                                        <Save className="h-4 w-4 mr-2" />
                                        {editingSize ? 'Update' : 'Create'}
                                    </Button>
                                    {editingSize && (
                                        <Button type="button" variant="outline" onClick={cancelEdit}>
                                            <X className="h-4 w-4 mr-2" />
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </Card>
                    </div>
                </div>

                {/* Mobile: Stacked Layout with Tabs */}
                <div className="md:hidden space-y-4">
                    {activeTab === 'categories' && (
                        <div className="space-y-4">
                            <h2 className="text-xl font-semibold flex items-center gap-2">
                                <Tag className="h-5 w-5" />
                                Categories
                            </h2>

                            {/* Mobile Categories List */}
                            <div className="space-y-3">
                                {categories.map((category) => (
                                    <Card key={category.id} className="p-4">
                                        <div className="flex items-start justify-between mb-2">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">{category.name}</span>
                                                    {!category.is_active && (
                                                        <Badge variant="secondary" className="bg-gray-100 text-gray-800">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">
                                                    {category.products_count} products
                                                </p>
                                                {category.description && (
                                                    <p className="text-sm text-muted-foreground mt-1">
                                                        {category.description}
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => startEditCategory(category)}
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => deleteCategory(category.id)}
                                                    className="text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </Card>
                                ))}
                            </div>

                            {/* Mobile Category Form */}
                            <Card className="p-4">
                                <h3 className="text-lg font-medium mb-4">
                                    {editingCategory ? 'Edit Category' : 'Add Category'}
                                </h3>
                                <form onSubmit={handleCategorySubmit} className="space-y-4">
                                    <div>
                                        <Label htmlFor="mobile-category-name">Name</Label>
                                        <Input
                                            id="mobile-category-name"
                                            value={categoryForm.data.name}
                                            onChange={(e) => categoryForm.setData('name', e.target.value)}
                                            required
                                        />
                                        {categoryForm.errors.name && (
                                            <p className="text-sm text-red-600 mt-1">{categoryForm.errors.name}</p>
                                        )}
                                    </div>

                                    <div>
                                        <Label htmlFor="mobile-category-description">Description</Label>
                                        <Textarea
                                            id="mobile-category-description"
                                            value={categoryForm.data.description}
                                            onChange={(e) => categoryForm.setData('description', e.target.value)}
                                            rows={3}
                                        />
                                    </div>

                                    <div className="flex items-center space-x-2">
                                        <Switch
                                            id="mobile-category-active"
                                            checked={categoryForm.data.is_active}
                                            onCheckedChange={(checked) => categoryForm.setData('is_active', Boolean(checked))}
                                        />
                                        <Label htmlFor="mobile-category-active">Active</Label>
                                    </div>

                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={categoryForm.processing} className="flex-1">
                                            <Save className="h-4 w-4 mr-2" />
                                            {editingCategory ? 'Update' : 'Create'}
                                        </Button>
                                        {editingCategory && (
                                            <Button type="button" variant="outline" onClick={cancelEdit}>
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </Card>
                        </div>
                    )}

                    {activeTab === 'sizes' && (
                        <div className="space-y-4">
                            <h2 className="text-xl font-semibold flex items-center gap-2">
                                <Ruler className="h-5 w-5" />
                                Sizes
                            </h2>

                            {/* Mobile Sizes List */}
                            <div className="space-y-3">
                                {sizes.map((size) => (
                                    <Card key={size.id} className="p-4">
                                        <div className="flex items-start justify-between mb-2">
                                            <div>
                                                <div className="flex items-center gap-2 mb-1">
                                                    <span className="font-medium">{size.name}</span>
                                                </div>
                                                <div className="text-sm text-muted-foreground space-y-1">
                                                    <p>Dimensions: {formatDimensions(size.length, size.width, size.height)} cm</p>
                                                    <p>Products: {size.products_count}</p>
                                                    <p>Shipping: {formatCurrency(size.shipping_cost)}</p>
                                                    <p>Type: {getBoxTypeName(size.box_type)}</p>
                                                </div>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => startEditSize(size)}
                                                >
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => deleteSize(size.id)}
                                                    className="text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    </Card>
                                ))}
                            </div>

                            {/* Mobile Size Form */}
                            <Card className="p-4">
                                <h3 className="text-lg font-medium mb-4">
                                    {editingSize ? 'Edit Size' : 'Add Size'}
                                </h3>
                                <form onSubmit={handleSizeSubmit} className="space-y-4">
                                    <div>
                                        <Label htmlFor="mobile-size-name">Name</Label>
                                        <Input
                                            id="mobile-size-name"
                                            value={sizeForm.data.name}
                                            onChange={(e) => sizeForm.setData('name', e.target.value)}
                                            required
                                        />
                                        {sizeForm.errors.name && (
                                            <p className="text-sm text-red-600 mt-1">{sizeForm.errors.name}</p>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-3 gap-2">
                                        <div>
                                            <Label htmlFor="mobile-size-length">Length (cm)</Label>
                                            <Input
                                                id="mobile-size-length"
                                                type="number"
                                                step="0.01"
                                                value={sizeForm.data.length}
                                                onChange={(e) => sizeForm.setData('length', parseFloat(e.target.value) || 0)}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="mobile-size-width">Width (cm)</Label>
                                            <Input
                                                id="mobile-size-width"
                                                type="number"
                                                step="0.01"
                                                value={sizeForm.data.width}
                                                onChange={(e) => sizeForm.setData('width', parseFloat(e.target.value) || 0)}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="mobile-size-height">Height (cm)</Label>
                                            <Input
                                                id="mobile-size-height"
                                                type="number"
                                                step="0.01"
                                                value={sizeForm.data.height}
                                                onChange={(e) => sizeForm.setData('height', parseFloat(e.target.value) || 0)}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="mobile-size-box-type">Box Type</Label>
                                        <Select
                                            value={sizeForm.data.box_type || "none"}
                                            onValueChange={(value) => sizeForm.setData('box_type', value === "none" ? "" : value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select box type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="none">No box type</SelectItem>
                                                {Object.entries(boxTypes).map(([key, label]) => (
                                                    <SelectItem key={key} value={key}>
                                                        {label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>

                                    <div>
                                        <Label htmlFor="mobile-size-shipping-cost">Shipping Cost</Label>
                                        <Input
                                            id="mobile-size-shipping-cost"
                                            type="number"
                                            step="0.01"
                                            value={sizeForm.data.shipping_cost}
                                            onChange={(e) => sizeForm.setData('shipping_cost', parseFloat(e.target.value) || 0)}
                                            required
                                        />
                                    </div>

                                    <div className="flex gap-2">
                                        <Button type="submit" disabled={sizeForm.processing} className="flex-1">
                                            <Save className="h-4 w-4 mr-2" />
                                            {editingSize ? 'Update' : 'Create'}
                                        </Button>
                                        {editingSize && (
                                            <Button type="button" variant="outline" onClick={cancelEdit}>
                                                <X className="h-4 w-4" />
                                            </Button>
                                        )}
                                    </div>
                                </form>
                            </Card>
                        </div>
                    )}
                </div>

                {/* Mobile Bottom Navigation */}
                <div className="md:hidden fixed bottom-0 left-0 right-0 bg-background border-t p-4 safe-area-pb">
                    <div className="flex justify-center gap-4">
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => setIsStatsOpen(!isStatsOpen)}
                            className="flex-1"
                        >
                            <BarChart3 className="h-4 w-4 mr-2" />
                            Stats
                        </Button>
                        <Button 
                            variant="outline" 
                            size="sm"
                            onClick={() => setActiveTab(activeTab === 'categories' ? 'sizes' : 'categories')}
                            className="flex-1"
                        >
                            {activeTab === 'categories' ? (
                                <>
                                    <Ruler className="h-4 w-4 mr-2" />
                                    Sizes
                                </>
                            ) : (
                                <>
                                    <Tag className="h-4 w-4 mr-2" />
                                    Categories
                                </>
                            )}
                        </Button>
                    </div>
                </div>
                
                {/* Add bottom padding to account for fixed navigation */}
                <div className="md:hidden h-20"></div>
            </div>
        </AdminLayout>
    );
}

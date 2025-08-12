import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { BarChart3, Box, ChevronDown, ChevronUp, Edit, GripVertical, Package, Ruler, Save, Tag, Trash2, TrendingUp, X, AlertTriangle } from 'lucide-react';
import { useState, useRef } from 'react';

import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

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
    
    // Delete dialog state
    const [deleteDialog, setDeleteDialog] = useState<{
        open: boolean;
        type: 'category' | 'size';
        item: Category | Size | null;
        canDelete: boolean;
    }>({
        open: false,
        type: 'category',
        item: null,
        canDelete: true,
    });

    // Form refs for scrolling
    const categoryFormRef = useRef<HTMLDivElement>(null);
    const sizeFormRef = useRef<HTMLDivElement>(null);
    const mobileCategoryFormRef = useRef<HTMLDivElement>(null);
    const mobileSizeFormRef = useRef<HTMLDivElement>(null);

    // Category form
    const categoryForm = useForm({
        name: '',
        description: '',
        image: '',
        is_active: true as boolean,
        sort_order: 0,
    });

    // Size form - Set random default box type
    const getRandomBoxType = () => {
        const boxTypeKeys = Object.keys(boxTypes);
        return boxTypeKeys[Math.floor(Math.random() * boxTypeKeys.length)];
    };

    const sizeForm = useForm({
        name: '',
        length: 1,
        width: 1,
        height: 1,
        box_type: getRandomBoxType(),
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

    const scrollToForm = (formType: 'category' | 'size') => {
        // Use setTimeout to ensure the form is rendered first
        setTimeout(() => {
            if (window.innerWidth >= 768) { // Desktop
                const ref = formType === 'category' ? categoryFormRef : sizeFormRef;
                ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else { // Mobile
                const ref = formType === 'category' ? mobileCategoryFormRef : mobileSizeFormRef;
                ref.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }, 100);
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
        scrollToForm('category');
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
        scrollToForm('size');
    };

    const cancelEdit = () => {
        setEditingCategory(null);
        setEditingSize(null);
        categoryForm.reset();
        sizeForm.reset();
    };

    const openDeleteDialog = (type: 'category' | 'size', item: Category | Size) => {
        const canDelete = item.products_count === 0;
        setDeleteDialog({
            open: true,
            type,
            item,
            canDelete,
        });
    };

    const closeDeleteDialog = () => {
        setDeleteDialog({
            open: false,
            type: 'category',
            item: null,
            canDelete: true,
        });
    };

    const confirmDelete = () => {
        if (!deleteDialog.item || !deleteDialog.canDelete) return;
        
        const endpoint = deleteDialog.type === 'category' 
            ? `/admin/categories/${deleteDialog.item.id}`
            : `/admin/sizes/${deleteDialog.item.id}`;
        
        router.delete(endpoint);
        closeDeleteDialog();
    };

    const deleteCategory = (category: Category) => {
        openDeleteDialog('category', category);
    };

    const deleteSize = (size: Size) => {
        openDeleteDialog('size', size);
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
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Categories & Sizes</h1>
                        <p className="text-sm text-muted-foreground md:text-base">Manage product taxonomy and logistics dimensions</p>
                    </div>
                </div>

                {/* Mobile Tab Switcher */}
                <div className="md:hidden">
                    <div className="flex rounded-lg border bg-muted p-1">
                        <button
                            onClick={() => setActiveTab('categories')}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                activeTab === 'categories' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Tag className="mr-2 inline h-4 w-4" />
                            Categories
                        </button>
                        <button
                            onClick={() => setActiveTab('sizes')}
                            className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                activeTab === 'sizes' ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground'
                            }`}
                        >
                            <Ruler className="mr-2 inline h-4 w-4" />
                            Sizes
                        </button>
                    </div>
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
                                    <Tag className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">Categories</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{categoryStats.total_categories}</p>
                                    <p className="text-xs text-muted-foreground">{categoryStats.active_categories} active</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Package className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">Categorized</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{categoryStats.products_with_categories}</p>
                                    <p className="text-xs text-muted-foreground">Products</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Ruler className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">Sizes</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{sizeStats.total_sizes}</p>
                                    <p className="text-xs text-muted-foreground">{sizeStats.sizes_with_products} with products</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Box className="h-4 w-4 text-orange-600" />
                                    <h3 className="text-xs font-medium">Avg. Shipping</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{formatCurrency(sizeStats.avg_shipping_cost)}</p>
                                    <p className="text-xs text-muted-foreground">Per package</p>
                                </div>
                            </Card>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                {/* Desktop: Two Column Layout */}
                <div className="hidden gap-6 md:grid md:grid-cols-2">
                    {/* Categories Section */}
                    <div className="space-y-4">
                        <div className="flex items-center justify-between">
                            <h2 className="flex items-center gap-2 text-xl font-semibold">
                                <Tag className="h-5 w-5" />
                                Categories
                            </h2>
                        </div>

                        {/* Categories List */}
                        {categories.length > 1 && (
                            <Card className="max-h-96 overflow-y-auto">
                                <div className="space-y-2 p-4">
                                    {categories.map((category) => (
                                        <div key={category.id} className="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/50">
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
                                                    <p className="text-sm text-muted-foreground">{category.products_count} products</p>
                                                </div>
                                            </div>
                                            <div className="flex items-center space-x-2">
                                                <Button variant="ghost" size="sm" onClick={() => startEditCategory(category)}>
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => deleteCategory(category)}
                                                    className="text-red-600 hover:text-red-700"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Card>
                        )}

                        {/* Category Form */}
                        <Card className="p-4" ref={categoryFormRef}>
                            <h3 className="mb-4 text-lg font-medium">{editingCategory ? 'Edit Category' : 'Add Category'}</h3>
                            <form onSubmit={handleCategorySubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="category-name">Name</Label>
                                    <Input
                                        id="category-name"
                                        value={categoryForm.data.name}
                                        onChange={(e) => categoryForm.setData('name', e.target.value)}
                                        required
                                    />
                                    {categoryForm.errors.name && <p className="mt-1 text-sm text-red-600">{categoryForm.errors.name}</p>}
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
                                        <Save className="mr-2 h-4 w-4" />
                                        {editingCategory ? 'Update' : 'Create'}
                                    </Button>
                                    {editingCategory && (
                                        <Button type="button" variant="outline" onClick={cancelEdit}>
                                            <X className="mr-2 h-4 w-4" />
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
                            <h2 className="flex items-center gap-2 text-xl font-semibold">
                                <Ruler className="h-5 w-5" />
                                Sizes
                            </h2>
                        </div>

                        {/* Sizes List */}
                        <Card className="max-h-96 overflow-y-auto">
                            <div className="space-y-2 p-4">
                                {sizes.map((size) => (
                                    <div key={size.id} className="flex items-center justify-between rounded-lg border p-3 hover:bg-muted/50">
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
                                            <Button variant="ghost" size="sm" onClick={() => startEditSize(size)}>
                                                <Edit className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => deleteSize(size)}
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
                        <Card className="p-4" ref={sizeFormRef}>
                            <h3 className="mb-4 text-lg font-medium">{editingSize ? 'Edit Size' : 'Add Size'}</h3>
                            <form onSubmit={handleSizeSubmit} className="space-y-4">
                                <div>
                                    <Label htmlFor="size-name">Name</Label>
                                    <Input
                                        id="size-name"
                                        value={sizeForm.data.name}
                                        onChange={(e) => sizeForm.setData('name', e.target.value)}
                                        required
                                    />
                                    {sizeForm.errors.name && <p className="mt-1 text-sm text-red-600">{sizeForm.errors.name}</p>}
                                </div>

                                <div className="grid grid-cols-3 gap-2">
                                    <div>
                                        <Label htmlFor="size-length">Length (cm)</Label>
                                        <Input
                                            id="size-length"
                                            type="number"
                                            step="0.1"
                                            min="0.1"
                                            value={sizeForm.data.length}
                                            onChange={(e) => sizeForm.setData('length', Math.max(0.1, parseFloat(e.target.value) || 0.1))}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="size-width">Width (cm)</Label>
                                        <Input
                                            id="size-width"
                                            type="number"
                                            step="0.1"
                                            min="0.1"
                                            value={sizeForm.data.width}
                                            onChange={(e) => sizeForm.setData('width', Math.max(0.1, parseFloat(e.target.value) || 0.1))}
                                            required
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="size-height">Height (cm)</Label>
                                        <Input
                                            id="size-height"
                                            type="number"
                                            step="0.1"
                                            min="0.1"
                                            value={sizeForm.data.height}
                                            onChange={(e) => sizeForm.setData('height', Math.max(0.1, parseFloat(e.target.value) || 0.1))}
                                            required
                                        />
                                    </div>
                                </div>

                                <div>
                                    <Label htmlFor="size-box-type">Box Type</Label>
                                    <Select
                                        value={sizeForm.data.box_type}
                                        onValueChange={(value) => sizeForm.setData('box_type', value)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select box type" />
                                        </SelectTrigger>
                                        <SelectContent>
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
                                        <Save className="mr-2 h-4 w-4" />
                                        {editingSize ? 'Update' : 'Create'}
                                    </Button>
                                    {editingSize && (
                                        <Button type="button" variant="outline" onClick={cancelEdit}>
                                            <X className="mr-2 h-4 w-4" />
                                            Cancel
                                        </Button>
                                    )}
                                </div>
                            </form>
                        </Card>
                    </div>
                </div>

                {/* Mobile: Stacked Layout with Tabs */}
                <div className="space-y-4 md:hidden">
                    {activeTab === 'categories' && (
                        <div className="space-y-4">
                            <h2 className="flex items-center gap-2 text-xl font-semibold">
                                <Tag className="h-5 w-5" />
                                Categories
                            </h2>

                            {/* Mobile Categories List */}
                            <div className="space-y-3">
                                {categories.map((category) => (
                                    <Card key={category.id} className="p-4">
                                        <div className="mb-2 flex items-start justify-between">
                                            <div>
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium">{category.name}</span>
                                                    {!category.is_active && (
                                                        <Badge variant="secondary" className="bg-gray-100 text-gray-800">
                                                            Inactive
                                                        </Badge>
                                                    )}
                                                </div>
                                                <p className="text-sm text-muted-foreground">{category.products_count} products</p>
                                                {category.description && <p className="mt-1 text-sm text-muted-foreground">{category.description}</p>}
                                            </div>
                                            <div className="flex gap-2">
                                                <Button variant="ghost" size="sm" onClick={() => startEditCategory(category)}>
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => deleteCategory(category)}
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
                            <Card className="p-4" ref={mobileCategoryFormRef}>
                                <h3 className="mb-4 text-lg font-medium">{editingCategory ? 'Edit Category' : 'Add Category'}</h3>
                                <form onSubmit={handleCategorySubmit} className="space-y-4">
                                    <div>
                                        <Label htmlFor="mobile-category-name">Name</Label>
                                        <Input
                                            id="mobile-category-name"
                                            value={categoryForm.data.name}
                                            onChange={(e) => categoryForm.setData('name', e.target.value)}
                                            required
                                        />
                                        {categoryForm.errors.name && <p className="mt-1 text-sm text-red-600">{categoryForm.errors.name}</p>}
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
                                            <Save className="mr-2 h-4 w-4" />
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
                            <h2 className="flex items-center gap-2 text-xl font-semibold">
                                <Ruler className="h-5 w-5" />
                                Sizes
                            </h2>

                            {/* Mobile Sizes List */}
                            <div className="space-y-3">
                                {sizes.map((size) => (
                                    <Card key={size.id} className="p-4">
                                        <div className="mb-2 flex items-start justify-between">
                                            <div>
                                                <div className="mb-1 flex items-center gap-2">
                                                    <span className="font-medium">{size.name}</span>
                                                </div>
                                                <div className="space-y-1 text-sm text-muted-foreground">
                                                    <p>Dimensions: {formatDimensions(size.length, size.width, size.height)} cm</p>
                                                    <p>Products: {size.products_count}</p>
                                                    <p>Shipping: {formatCurrency(size.shipping_cost)}</p>
                                                    <p>Type: {getBoxTypeName(size.box_type)}</p>
                                                </div>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button variant="ghost" size="sm" onClick={() => startEditSize(size)}>
                                                    <Edit className="h-4 w-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => deleteSize(size)}
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
                            <Card className="p-4" ref={mobileSizeFormRef}>
                                <h3 className="mb-4 text-lg font-medium">{editingSize ? 'Edit Size' : 'Add Size'}</h3>
                                <form onSubmit={handleSizeSubmit} className="space-y-4">
                                    <div>
                                        <Label htmlFor="mobile-size-name">Name</Label>
                                        <Input
                                            id="mobile-size-name"
                                            value={sizeForm.data.name}
                                            onChange={(e) => sizeForm.setData('name', e.target.value)}
                                            required
                                        />
                                        {sizeForm.errors.name && <p className="mt-1 text-sm text-red-600">{sizeForm.errors.name}</p>}
                                    </div>

                                    <div className="grid grid-cols-3 gap-2">
                                        <div>
                                            <Label htmlFor="mobile-size-length">Length (cm)</Label>
                                            <Input
                                                id="mobile-size-length"
                                                type="number"
                                                step="0.1"
                                                min="0.1"
                                                value={sizeForm.data.length}
                                                onChange={(e) => sizeForm.setData('length', Math.max(0.1, parseFloat(e.target.value) || 0.1))}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="mobile-size-width">Width (cm)</Label>
                                            <Input
                                                id="mobile-size-width"
                                                type="number"
                                                step="0.1"
                                                min="0.1"
                                                value={sizeForm.data.width}
                                                onChange={(e) => sizeForm.setData('width', Math.max(0.1, parseFloat(e.target.value) || 0.1))}
                                                required
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="mobile-size-height">Height (cm)</Label>
                                            <Input
                                                id="mobile-size-height"
                                                type="number"
                                                step="0.1"
                                                min="0.1"
                                                value={sizeForm.data.height}
                                                onChange={(e) => sizeForm.setData('height', Math.max(0.1, parseFloat(e.target.value) || 0.1))}
                                                required
                                            />
                                        </div>
                                    </div>

                                    <div>
                                        <Label htmlFor="mobile-size-box-type">Box Type</Label>
                                        <Select
                                            value={sizeForm.data.box_type}
                                            onValueChange={(value) => sizeForm.setData('box_type', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select box type" />
                                            </SelectTrigger>
                                            <SelectContent>
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
                                            <Save className="mr-2 h-4 w-4" />
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

                {/* Add bottom padding to account for fixed navigation */}
                <div className="h-10 md:hidden"></div>

                {/* Mobile Bottom Navigation */}
                <div className="fixed inset-x-0 bottom-0 z-50 border-t bg-background p-4 md:hidden">
                    <div className="flex justify-center gap-4">
                        <Button variant="outline" size="sm" onClick={() => setIsStatsOpen(!isStatsOpen)} className="flex-1">
                            <BarChart3 className="mr-2 h-4 w-4" />
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
                                    <Ruler className="mr-2 h-4 w-4" />
                                    Sizes
                                </>
                            ) : (
                                <>
                                    <Tag className="mr-2 h-4 w-4" />
                                    Categories
                                </>
                            )}
                        </Button>
                    </div>
                </div>


                {/* Delete Confirmation Dialog */}
                <Dialog open={deleteDialog.open} onOpenChange={closeDeleteDialog}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <AlertTriangle className="h-5 w-5 text-amber-500" />
                                Delete {deleteDialog.type === 'category' ? 'Category' : 'Size'}
                            </DialogTitle>
                            <DialogDescription>
                                {deleteDialog.canDelete ? (
                                    `Are you sure you want to delete "${deleteDialog.item?.name}"? This action cannot be undone.`
                                ) : (
                                    `Cannot delete "${deleteDialog.item?.name}" because it has ${deleteDialog.item?.products_count} associated products.`
                                )}
                            </DialogDescription>
                        </DialogHeader>
                        
                        {!deleteDialog.canDelete && (
                            <Alert>
                                <AlertTriangle className="h-4 w-4" />
                                <AlertDescription>
                                    To delete this {deleteDialog.type}, please first remove or reassign all associated products to another {deleteDialog.type}.
                                </AlertDescription>
                            </Alert>
                        )}

                        <DialogFooter>
                            <Button variant="outline" onClick={closeDeleteDialog}>
                                Cancel
                            </Button>
                            {deleteDialog.canDelete && (
                                <Button variant="destructive" onClick={confirmDelete}>
                                    <Trash2 className="mr-2 h-4 w-4" />
                                    Delete
                                </Button>
                            )}
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AdminLayout>
    );
}

import { VariantManager } from '@/components/admin/VariantManager';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { Head, useForm } from '@inertiajs/react';
import { ChevronDown, Package, Settings, Star } from 'lucide-react';
import { useState } from 'react';

interface ValidationErrors {
    [key: string]: boolean;
}
import { route } from 'ziggy-js';

interface FormData {
    name: string;
    category_id: string;
    size_id: string;
    status: string;
    featured: boolean;
    base_sku: string;
    seo_title: string;
    seo_description: string;
    variants: Array<{
        name: string;
        sku_suffix: string;
        price: string;
        stock_quantity: string;
        description: string;
        is_default: boolean;
        sort_order: number;
        images: File[];
    }>;
}

interface Category {
    id: number;
    name: string;
}

interface Size {
    id: number;
    name: string;
}

interface Props {
    categories: Category[];
    sizes: Size[];
    uploadLimits: {
        maxFileSize: string;
        maxFileSizeBytes: number;
        maxRequestSize: string;
        maxRequestSizeBytes: number;
        maxFiles: number;
        maxFilesPerVariant: number;
    };
}

export default function CreateProduct({ categories, sizes, uploadLimits }: Props) {
    const [isAdvancedOpen, setIsAdvancedOpen] = useState(false);

    const { data, setData, post, processing, errors } = useForm<FormData>({
        name: '',
        category_id: '',
        size_id: '',
        status: 'active',
        featured: false,
        base_sku: '',
        seo_title: '',
        seo_description: '',
        variants: [
            {
                name: 'Standard',
                sku_suffix: '',
                price: '',
                stock_quantity: '',
                description: '',
                is_default: true,
                sort_order: 0,
                images: [],
            },
        ],
    });

    const [validationErrors, setValidationErrors] = useState<Record<string, boolean>>({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Client-side validation with field highlighting
        const errors: Record<string, boolean> = {};

        if (!data.name.trim()) {
            errors['name'] = true;
        }
        if (!data.category_id) {
            errors['category_id'] = true;
        }
        if (!data.size_id) {
            errors['size_id'] = true;
        }

        // Validate variants
        data.variants.forEach((v, index) => {
            if (!v.name) errors[`variant_${index}_name`] = true;
            if (!v.price) errors[`variant_${index}_price`] = true;
            if (!v.stock_quantity) errors[`variant_${index}_stock`] = true;
        });

        if (data.variants.some((v) => !v.name || !v.price || !v.stock_quantity)) {
            setValidationErrors(errors);
            return;
        }

        if (Object.keys(errors).length > 0) {
            setValidationErrors(errors);
            return;
        }

        setValidationErrors({});

        // Create FormData for file uploads
        const formData = new FormData();
        formData.append('name', data.name);
        formData.append('category_id', data.category_id);
        formData.append('size_id', data.size_id);
        formData.append('status', data.status);
        formData.append('featured', data.featured ? '1' : '0');
        formData.append('base_sku', data.base_sku);
        formData.append('seo_title', data.seo_title);
        formData.append('seo_description', data.seo_description);

        // Add variants as JSON (without images)
        const variantsForJson = data.variants.map((v, index) => ({
            name: v.name,
            sku_suffix: v.sku_suffix,
            price: v.price,
            stock_quantity: v.stock_quantity,
            description: v.description,
            is_default: v.is_default,
            sort_order: v.sort_order,
        }));
        formData.append('variants', JSON.stringify(variantsForJson));

        // Add images separately
        data.variants.forEach((variant, index) => {
            variant.images.forEach((file) => {
                formData.append(`variant_images_${index}[]`, file);
            });
        });

        post(route('admin.products.store'), {
            data: formData,
            forceFormData: true,
        });
    };

    return (
        <AdminLayout>
            <Head title="Create Product" />

            <div className="space-y-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Create Product</h1>
                        <p className="text-gray-500">Add a new product to your inventory</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Error Display */}
                    {(errors.error || errors.name) && (
                        <div className="rounded-md border border-red-200 bg-red-50 p-4">
                            <p className="text-sm text-red-600">{errors.error || errors.name}</p>
                        </div>
                    )}

                    {/* Basic Information */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Package className="h-5 w-5 text-blue-600" />
                                <CardTitle className="text-lg">Basic Information</CardTitle>
                            </div>
                            <CardDescription>Essential product details and inventory management</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <Label htmlFor="name">Product Name *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Enter product name"
                                        className={errors.name ? 'border-red-500' : ''}
                                    />
                                    {errors.name && <p className="mt-1 text-sm text-red-500">{errors.name}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="category_id">Category *</Label>
                                    <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                                        <SelectTrigger className={errors.category_id ? 'border-red-500' : ''}>
                                            <SelectValue placeholder="Select category" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem key={category.id} value={category.id.toString()}>
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.category_id && <p className="mt-1 text-sm text-red-500">{errors.category_id}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="size_id">Size *</Label>
                                    <Select value={data.size_id} onValueChange={(value) => setData('size_id', value)}>
                                        <SelectTrigger className={errors.size_id ? 'border-red-500' : ''}>
                                            <SelectValue placeholder="Select size" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sizes.map((size) => (
                                                <SelectItem key={size.id} value={size.id.toString()}>
                                                    {size.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {errors.size_id && <p className="mt-1 text-sm text-red-500">{errors.size_id}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="base_sku">Base SKU</Label>
                                    <Input
                                        id="base_sku"
                                        value={data.base_sku}
                                        onChange={(e) => setData('base_sku', e.target.value)}
                                        placeholder="Enter base SKU (e.g., PROD-001)"
                                        className={errors.base_sku ? 'border-red-500' : ''}
                                    />
                                    {errors.base_sku && <p className="mt-1 text-sm text-red-500">{errors.base_sku}</p>}
                                    <p className="mt-1 text-xs text-gray-500">SKU prefix for all variants (e.g., PROD-001-RED)</p>
                                </div>

                                <div>
                                    <Label htmlFor="status">Status</Label>
                                    <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-2 rounded-full bg-green-500"></div>
                                                    Active
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-2 rounded-full bg-gray-500"></div>
                                                    Inactive
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="draft">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-2 rounded-full bg-yellow-500"></div>
                                                    Draft
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="featured"
                                        checked={data.featured}
                                        onCheckedChange={(checked) => setData('featured', Boolean(checked))}
                                    />
                                    <Label htmlFor="featured" className="flex cursor-pointer items-center gap-2">
                                        <Star className="h-4 w-4 text-yellow-500" />
                                        Featured Product
                                    </Label>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Variants */}
                    <VariantManager
                        variants={data.variants}
                        baseSku={data.base_sku}
                        onVariantsChange={(variants) => setData('variants', variants)}
                        validationErrors={validationErrors}
                        uploadLimits={{
                            maxFileSize: uploadLimits.maxFileSize,
                            maxFileSizeBytes: uploadLimits.maxFileSizeBytes,
                            maxFilesPerVariant: uploadLimits.maxFilesPerVariant,
                        }}
                    />

                    {/* Advanced Options */}
                    <Collapsible open={isAdvancedOpen} onOpenChange={setIsAdvancedOpen}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer transition-colors hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Settings className="h-5 w-5 text-green-600" />
                                            <CardTitle className="text-lg">Advanced Options</CardTitle>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${isAdvancedOpen ? 'rotate-180' : ''}`} />
                                    </div>
                                    <CardDescription>Optional settings for pricing and inventory management</CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="pt-0">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="md:col-span-2">
                                            <Label htmlFor="seo_title">SEO Title</Label>
                                            <Input
                                                id="seo_title"
                                                value={data.seo_title}
                                                onChange={(e) => setData('seo_title', e.target.value)}
                                                placeholder="SEO optimized title"
                                                className={errors.seo_title ? 'border-red-500' : ''}
                                            />
                                            {errors.seo_title && <p className="mt-1 text-sm text-red-500">{errors.seo_title}</p>}
                                        </div>

                                        <div className="md:col-span-2">
                                            <Label htmlFor="seo_description">SEO Description</Label>
                                            <Textarea
                                                id="seo_description"
                                                value={data.seo_description}
                                                onChange={(e) => setData('seo_description', e.target.value)}
                                                placeholder="SEO optimized description"
                                                rows={3}
                                                className={errors.seo_description ? 'border-red-500' : ''}
                                            />
                                            {errors.seo_description && <p className="mt-1 text-sm text-red-500">{errors.seo_description}</p>}
                                        </div>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Submit Button */}
                    <div className="flex flex-col gap-3 pt-4 sm:flex-row">
                        <Button type="submit" disabled={processing} className="order-2 w-full sm:order-1 sm:w-auto">
                            {processing ? 'Creating...' : 'Create Product'}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => window.history.back()}
                            className="order-1 w-full sm:order-2 sm:w-auto"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

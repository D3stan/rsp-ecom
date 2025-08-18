import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AdminLayout from '@/layouts/admin-layout';
import { Head, useForm, router } from '@inertiajs/react';
import { ChevronDown, DollarSign, Image as ImageIcon, Package, Star, Upload, X } from 'lucide-react';
import { useState } from 'react';
import { route } from 'ziggy-js';

interface Category {
    id: number;
    name: string;
}

interface Size {
    id: number;
    name: string;
}

interface Product {
    id: number;
    name: string;
    description: string;
    price: number;
    compare_price: number | null;
    stock_quantity: number;
    sku: string;
    status: string;
    featured: boolean;
    category_id: number;
    size_id: number;
    images: string[];
    image_urls: string[];
    main_image_url: string;
}

interface Props {
    product: Product;
    categories: Category[];
    sizes: Size[];
    uploadLimits: {
        maxFileSize: string;
        maxFiles: number;
    };
}

export default function EditProduct({ product, categories, sizes, uploadLimits }: Props) {
    const [isAdvancedOpen, setIsAdvancedOpen] = useState(false);
    const [previewImages, setPreviewImages] = useState<string[]>(product.image_urls || []);

    const { data, setData, put, processing, errors } = useForm({
        name: product.name,
        description: product.description || '',
        price: product.price.toString(),
        compare_price: product.compare_price?.toString() || '',
        stock_quantity: product.stock_quantity.toString(),
        sku: product.sku,
        status: product.status,
        featured: Boolean(product.featured),
        category_id: product.category_id.toString(),
        size_id: product.size_id.toString(),
        new_images: [] as File[],
    });

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        const newImages = [...data.new_images, ...files].slice(0, 10);

        setData('new_images', newImages);

        // Create preview URLs
        const newPreviews = files.map((file) => URL.createObjectURL(file));
        setPreviewImages((prev) => [...prev, ...newPreviews].slice(0, 10));
    };

    const removeImage = (index: number) => {
        const newImages = data.new_images.filter((_, i) => i !== index);
        const newPreviews = previewImages.filter((_, i) => i !== index);

        setData('new_images', newImages);
        setPreviewImages(newPreviews);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        
        console.log('Form data being sent:', data);
        
        // Use POST method with _method field for file uploads (Laravel hack)
        router.post(route('admin.products.update', product.id), {
            ...data,
            _method: 'PUT',
        }, {
            preserveScroll: true,
            onError: (errors: any) => {
                console.error('Validation errors:', errors);
            },
            onSuccess: () => {
                console.log('Product updated successfully');
            },
        });
    };

    return (
        <AdminLayout>
            <Head title={`Edit ${product.name}`} />

            <div className="space-y-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Edit Product</h1>
                        <p className="text-gray-500">Update product information</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
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

                                <div>
                                    <Label htmlFor="price">Price *</Label>
                                    <div className="relative">
                                        <DollarSign className="absolute top-3 left-3 h-4 w-4 text-gray-400" />
                                        <Input
                                            id="price"
                                            type="number"
                                            step="0.01"
                                            value={data.price}
                                            onChange={(e) => setData('price', e.target.value)}
                                            placeholder="0.00"
                                            className={`pl-10 ${errors.price ? 'border-red-500' : ''}`}
                                        />
                                    </div>
                                    {errors.price && <p className="mt-1 text-sm text-red-500">{errors.price}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="stock_quantity">Stock Quantity *</Label>
                                    <Input
                                        id="stock_quantity"
                                        type="number"
                                        value={data.stock_quantity}
                                        onChange={(e) => setData('stock_quantity', e.target.value)}
                                        placeholder="0"
                                        className={errors.stock_quantity ? 'border-red-500' : ''}
                                    />
                                    {errors.stock_quantity && <p className="mt-1 text-sm text-red-500">{errors.stock_quantity}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="description">Description</Label>
                                    <Textarea
                                        id="description"
                                        value={data.description}
                                        onChange={(e) => setData('description', e.target.value)}
                                        placeholder="Enter product description"
                                        rows={3}
                                        className={errors.description ? 'border-red-500' : ''}
                                    />
                                    {errors.description && <p className="mt-1 text-sm text-red-500">{errors.description}</p>}
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

                    {/* Product Images */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <ImageIcon className="h-5 w-5 text-purple-600" />
                                <CardTitle className="text-lg">Product Images</CardTitle>
                            </div>
                            <CardDescription>Upload up to {uploadLimits.maxFiles} images (JPEG, PNG, GIF, WebP - max {uploadLimits.maxFileSize} each)</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {/* Upload Area */}
                                <div className="rounded-lg border-2 border-dashed border-gray-300 p-6 text-center">
                                    <input
                                        type="file"
                                        id="images"
                                        multiple
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        className="hidden"
                                        disabled={data.new_images.length >= 10}
                                    />
                                    <label
                                        htmlFor="images"
                                        className={`cursor-pointer ${data.new_images.length >= 10 ? 'cursor-not-allowed opacity-50' : ''}`}
                                    >
                                        <Upload className="mx-auto h-12 w-12 text-gray-400" />
                                        <p className="mt-2 text-sm text-gray-600">
                                            {data.new_images.length >= 10 ? 'Maximum 10 images reached' : 'Click to upload new images'}
                                        </p>
                                        <p className="text-xs text-gray-500">{data.new_images.length}/10 new images</p>
                                    </label>
                                </div>

                                {/* Current Images */}
                                {previewImages.length > 0 && (
                                    <div>
                                        <h4 className="mb-3 text-sm font-medium text-gray-700">Current Images</h4>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                            {previewImages.map((preview, index) => (
                                                <div key={index} className="group relative">
                                                    <div className="aspect-square overflow-hidden rounded-lg bg-gray-100">
                                                        <img
                                                            src={preview.startsWith('blob:') ? preview : preview}
                                                            alt={`Product ${index + 1}`}
                                                            className="h-full w-full object-cover"
                                                        />
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeImage(index)}
                                                        className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </button>
                                                    {index === 0 && <Badge className="absolute bottom-2 left-2 bg-blue-500">Main</Badge>}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {errors.new_images && <p className="text-sm text-red-500">{errors.new_images}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Advanced Options */}
                    <Collapsible open={isAdvancedOpen} onOpenChange={setIsAdvancedOpen}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer transition-colors hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5 text-green-600" />
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
                                        <div>
                                            <Label htmlFor="sku">SKU</Label>
                                            <Input
                                                id="sku"
                                                value={data.sku}
                                                onChange={(e) => setData('sku', e.target.value)}
                                                placeholder="Product SKU"
                                                className={errors.sku ? 'border-red-500' : ''}
                                            />
                                            {errors.sku && <p className="mt-1 text-sm text-red-500">{errors.sku}</p>}
                                        </div>

                                        <div>
                                            <Label htmlFor="compare_price">Compare Price</Label>
                                            <div className="relative">
                                                <DollarSign className="absolute top-3 left-3 h-4 w-4 text-gray-400" />
                                                <Input
                                                    id="compare_price"
                                                    type="number"
                                                    step="0.01"
                                                    value={data.compare_price}
                                                    onChange={(e) => setData('compare_price', e.target.value)}
                                                    placeholder="0.00"
                                                    className={`pl-10 ${errors.compare_price ? 'border-red-500' : ''}`}
                                                />
                                            </div>
                                            {errors.compare_price && <p className="mt-1 text-sm text-red-500">{errors.compare_price}</p>}
                                            <p className="mt-1 text-xs text-gray-500">Original price for discount display</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Submit Button */}
                    <div className="flex flex-col gap-3 pt-4 sm:flex-row">
                        <Button type="submit" disabled={processing} className="order-2 w-full sm:order-1 sm:w-auto">
                            {processing ? 'Updating...' : 'Update Product'}
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

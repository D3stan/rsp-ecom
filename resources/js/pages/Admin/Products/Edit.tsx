import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { ChevronDown, Upload, X, Star, Package, DollarSign, Image as ImageIcon } from 'lucide-react';

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
}

export default function EditProduct({ product, categories, sizes }: Props) {
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
        featured: product.featured as boolean,
        category_id: product.category_id.toString(),
        size_id: product.size_id.toString(),
        images: [] as File[],
    });

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        const newImages = [...data.images, ...files].slice(0, 10);
        
        setData('images', newImages);
        
        // Create preview URLs
        const newPreviews = files.map(file => URL.createObjectURL(file));
        setPreviewImages(prev => [...prev, ...newPreviews].slice(0, 10));
    };

    const removeImage = (index: number) => {
        const newImages = data.images.filter((_, i) => i !== index);
        const newPreviews = previewImages.filter((_, i) => i !== index);
        
        setData('images', newImages);
        setPreviewImages(newPreviews);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(route('admin.products.update', product.id));
    };

    return (
        <AdminLayout>
            <Head title={`Edit ${product.name}`} />
            
            <div className="p-4 sm:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
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
                            <CardDescription>
                                Essential product details and inventory management
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="md:col-span-2">
                                    <Label htmlFor="name">Product Name *</Label>
                                    <Input
                                        id="name"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="Enter product name"
                                        className={errors.name ? 'border-red-500' : ''}
                                    />
                                    {errors.name && <p className="text-sm text-red-500 mt-1">{errors.name}</p>}
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
                                    {errors.category_id && <p className="text-sm text-red-500 mt-1">{errors.category_id}</p>}
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
                                    {errors.size_id && <p className="text-sm text-red-500 mt-1">{errors.size_id}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="price">Price *</Label>
                                    <div className="relative">
                                        <DollarSign className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
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
                                    {errors.price && <p className="text-sm text-red-500 mt-1">{errors.price}</p>}
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
                                    {errors.stock_quantity && <p className="text-sm text-red-500 mt-1">{errors.stock_quantity}</p>}
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
                                    {errors.description && <p className="text-sm text-red-500 mt-1">{errors.description}</p>}
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
                                                    <div className="w-2 h-2 bg-green-500 rounded-full"></div>
                                                    Active
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-2 h-2 bg-gray-500 rounded-full"></div>
                                                    Inactive
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="draft">
                                                <div className="flex items-center gap-2">
                                                    <div className="w-2 h-2 bg-yellow-500 rounded-full"></div>
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
                                    <Label htmlFor="featured" className="flex items-center gap-2 cursor-pointer">
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
                            <CardDescription>
                                Upload up to 10 images (JPEG, PNG, GIF, WebP - max 2MB each)
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                {/* Upload Area */}
                                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                                    <input
                                        type="file"
                                        id="images"
                                        multiple
                                        accept="image/*"
                                        onChange={handleImageChange}
                                        className="hidden"
                                        disabled={data.images.length >= 10}
                                    />
                                    <label
                                        htmlFor="images"
                                        className={`cursor-pointer ${data.images.length >= 10 ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    >
                                        <Upload className="mx-auto h-12 w-12 text-gray-400" />
                                        <p className="mt-2 text-sm text-gray-600">
                                            {data.images.length >= 10 ? 'Maximum 10 images reached' : 'Click to upload new images'}
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            {data.images.length}/10 new images
                                        </p>
                                    </label>
                                </div>

                                {/* Current Images */}
                                {previewImages.length > 0 && (
                                    <div>
                                        <h4 className="text-sm font-medium text-gray-700 mb-3">Current Images</h4>
                                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                                            {previewImages.map((preview, index) => (
                                                <div key={index} className="relative group">
                                                    <div className="aspect-square rounded-lg overflow-hidden bg-gray-100">
                                                        <img
                                                            src={preview.startsWith('blob:') ? preview : preview}
                                                            alt={`Product ${index + 1}`}
                                                            className="w-full h-full object-cover"
                                                        />
                                                    </div>
                                                    <button
                                                        type="button"
                                                        onClick={() => removeImage(index)}
                                                        className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                                    >
                                                        <X className="h-4 w-4" />
                                                    </button>
                                                    {index === 0 && (
                                                        <Badge className="absolute bottom-2 left-2 bg-blue-500">
                                                            Main
                                                        </Badge>
                                                    )}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}

                                {errors.images && <p className="text-sm text-red-500">{errors.images}</p>}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Advanced Options */}
                    <Collapsible open={isAdvancedOpen} onOpenChange={setIsAdvancedOpen}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-gray-50 transition-colors">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5 text-green-600" />
                                            <CardTitle className="text-lg">Advanced Options</CardTitle>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${isAdvancedOpen ? 'rotate-180' : ''}`} />
                                    </div>
                                    <CardDescription>
                                        Optional settings for pricing and inventory management
                                    </CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="pt-0">
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="sku">SKU</Label>
                                            <Input
                                                id="sku"
                                                value={data.sku}
                                                onChange={(e) => setData('sku', e.target.value)}
                                                placeholder="Product SKU"
                                                className={errors.sku ? 'border-red-500' : ''}
                                            />
                                            {errors.sku && <p className="text-sm text-red-500 mt-1">{errors.sku}</p>}
                                        </div>

                                        <div>
                                            <Label htmlFor="compare_price">Compare Price</Label>
                                            <div className="relative">
                                                <DollarSign className="absolute left-3 top-3 h-4 w-4 text-gray-400" />
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
                                            {errors.compare_price && <p className="text-sm text-red-500 mt-1">{errors.compare_price}</p>}
                                            <p className="text-xs text-gray-500 mt-1">
                                                Original price for discount display
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Submit Button */}
                    <div className="flex flex-col sm:flex-row gap-3 pt-4">
                        <Button
                            type="submit"
                            disabled={processing}
                            className="w-full sm:w-auto order-2 sm:order-1"
                        >
                            {processing ? 'Updating...' : 'Update Product'}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => window.history.back()}
                            className="w-full sm:w-auto order-1 sm:order-2"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

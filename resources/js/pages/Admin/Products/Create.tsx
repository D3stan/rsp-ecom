import React, { useState } from 'react';
import { useForm, Head } from '@inertiajs/react';
import { route } from 'ziggy-js';
import AdminLayout from '@/layouts/admin-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { X, Upload } from 'lucide-react';

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
}

export default function ProductForm({ categories, sizes }: Props) {
    const [previewImages, setPreviewImages] = useState<string[]>([]);
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        description: '',
        price: '',
        compare_price: '',
        stock_quantity: '',
        sku: '',
        status: 'active',
        featured: false as boolean,
        category_id: '',
        size_id: '',
        images: [] as File[],
    });

    const handleImageChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(e.target.files || []);
        const maxImages = 10;
        const currentImageCount = data.images.length;
        
        if (currentImageCount + files.length > maxImages) {
            alert(`You can only upload up to ${maxImages} images total.`);
            return;
        }

        // Add new images to existing ones
        const newImages = [...data.images, ...files];
        setData('images', newImages);

        // Create preview URLs for new images
        const newPreviews = files.map(file => URL.createObjectURL(file));
        setPreviewImages(prev => [...prev, ...newPreviews]);
    };

    const removeImage = (index: number) => {
        const newImages = data.images.filter((_, i) => i !== index);
        setData('images', newImages);
        
        // Clean up preview URL
        URL.revokeObjectURL(previewImages[index]);
        setPreviewImages(prev => prev.filter((_, i) => i !== index));
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        
        const formData = new FormData();
        
        // Append all form fields
        Object.entries(data).forEach(([key, value]) => {
            if (key === 'images') {
                // Append each image file
                (value as File[]).forEach(file => {
                    formData.append('images[]', file);
                });
            } else {
                formData.append(key, value as string);
            }
        });

        post(route('admin.products.store'), {
            forceFormData: true,
            onSuccess: () => {
                reset();
                setPreviewImages([]);
            },
        });
    };

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Dashboard', href: '/admin/dashboard' },
                { title: 'Products', href: '/admin/products' },
                { title: 'Create Product', href: '/admin/products/create' },
            ]}
        >
            <Head title="Create Product" />

            <div className="space-y-6">
                {/* Page Header */}
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Create Product</h1>
                    <p className="text-muted-foreground">
                        Add a new product to your catalog
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Product Information</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-6">
                        {/* Basic Information */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="name">Product Name *</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Enter product name"
                                    className={errors.name ? 'border-red-500' : ''}
                                />
                                {errors.name && <p className="text-red-500 text-sm mt-1">{errors.name}</p>}
                            </div>

                            <div>
                                <Label htmlFor="sku">SKU *</Label>
                                <Input
                                    id="sku"
                                    type="text"
                                    value={data.sku}
                                    onChange={(e) => setData('sku', e.target.value)}
                                    placeholder="Enter product SKU"
                                    className={errors.sku ? 'border-red-500' : ''}
                                />
                                {errors.sku && <p className="text-red-500 text-sm mt-1">{errors.sku}</p>}
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Enter product description"
                                rows={4}
                                className={errors.description ? 'border-red-500' : ''}
                            />
                            {errors.description && <p className="text-red-500 text-sm mt-1">{errors.description}</p>}
                        </div>

                        {/* Pricing */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <Label htmlFor="price">Price *</Label>
                                <Input
                                    id="price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.price}
                                    onChange={(e) => setData('price', e.target.value)}
                                    placeholder="0.00"
                                    className={errors.price ? 'border-red-500' : ''}
                                />
                                {errors.price && <p className="text-red-500 text-sm mt-1">{errors.price}</p>}
                            </div>

                            <div>
                                <Label htmlFor="compare_price">Compare Price</Label>
                                <Input
                                    id="compare_price"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={data.compare_price}
                                    onChange={(e) => setData('compare_price', e.target.value)}
                                    placeholder="0.00"
                                    className={errors.compare_price ? 'border-red-500' : ''}
                                />
                                {errors.compare_price && <p className="text-red-500 text-sm mt-1">{errors.compare_price}</p>}
                            </div>

                            <div>
                                <Label htmlFor="stock_quantity">Stock Quantity *</Label>
                                <Input
                                    id="stock_quantity"
                                    type="number"
                                    min="0"
                                    value={data.stock_quantity}
                                    onChange={(e) => setData('stock_quantity', e.target.value)}
                                    placeholder="0"
                                    className={errors.stock_quantity ? 'border-red-500' : ''}
                                />
                                {errors.stock_quantity && <p className="text-red-500 text-sm mt-1">{errors.stock_quantity}</p>}
                            </div>
                        </div>

                        {/* Category and Size */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="category_id">Category *</Label>
                                <Select value={data.category_id} onValueChange={(value) => setData('category_id', value)}>
                                    <SelectTrigger className={errors.category_id ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="Select a category" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((category) => (
                                            <SelectItem key={category.id} value={category.id.toString()}>
                                                {category.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category_id && <p className="text-red-500 text-sm mt-1">{errors.category_id}</p>}
                            </div>

                            <div>
                                <Label htmlFor="size_id">Size *</Label>
                                <Select value={data.size_id} onValueChange={(value) => setData('size_id', value)}>
                                    <SelectTrigger className={errors.size_id ? 'border-red-500' : ''}>
                                        <SelectValue placeholder="Select a size" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {sizes.map((size) => (
                                            <SelectItem key={size.id} value={size.id.toString()}>
                                                {size.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.size_id && <p className="text-red-500 text-sm mt-1">{errors.size_id}</p>}
                            </div>
                        </div>

                        {/* Status and Featured */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="status">Status</Label>
                                <Select value={data.status} onValueChange={(value) => setData('status', value)}>
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="active">Active</SelectItem>
                                        <SelectItem value="inactive">Inactive</SelectItem>
                                        <SelectItem value="draft">Draft</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center space-x-2 pt-6">
                                <input
                                    id="featured"
                                    type="checkbox"
                                    checked={data.featured}
                                    onChange={(e) => setData('featured', e.target.checked)}
                                    className="rounded border-gray-300"
                                />
                                <Label htmlFor="featured">Featured Product</Label>
                            </div>
                        </div>

                        {/* Image Upload */}
                        <div>
                            <Label htmlFor="images">Product Images (Max: 10)</Label>
                            <div className="mt-2">
                                <div className="flex items-center justify-center w-full">
                                    <label htmlFor="images" className="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                        <div className="flex flex-col items-center justify-center pt-5 pb-6">
                                            <Upload className="w-8 h-8 mb-4 text-gray-500" />
                                            <p className="mb-2 text-sm text-gray-500">
                                                <span className="font-semibold">Click to upload</span> product images
                                            </p>
                                            <p className="text-xs text-gray-500">PNG, JPG, GIF, WEBP up to 2MB each</p>
                                        </div>
                                        <input
                                            id="images"
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            onChange={handleImageChange}
                                            className="hidden"
                                        />
                                    </label>
                                </div>
                                {errors.images && <p className="text-red-500 text-sm mt-1">{errors.images}</p>}
                            </div>

                            {/* Image Previews */}
                            {previewImages.length > 0 && (
                                <div className="mt-4">
                                    <Label>Image Previews ({previewImages.length}/10)</Label>
                                    <div className="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mt-2">
                                        {previewImages.map((preview, index) => (
                                            <div key={index} className="relative group">
                                                <img
                                                    src={preview}
                                                    alt={`Preview ${index + 1}`}
                                                    className="w-full h-24 object-cover rounded-lg"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => removeImage(index)}
                                                    className="absolute -top-2 -right-2 bg-red-500 text-white rounded-full p-1 opacity-0 group-hover:opacity-100 transition-opacity"
                                                >
                                                    <X className="w-4 h-4" />
                                                </button>
                                                {index === 0 && (
                                                    <Badge className="absolute bottom-1 left-1 text-xs">Main</Badge>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Submit */}
                        <div className="flex justify-end space-x-4">
                            <Button type="button" variant="outline" onClick={() => window.history.back()}>
                                Cancel
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Creating...' : 'Create Product'}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
            </div>
        </AdminLayout>
    );
}

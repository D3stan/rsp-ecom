import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Plus, Trash2, GripVertical, Star, X } from 'lucide-react';
import { useState } from 'react';

interface Variant {
    id?: number;
    name: string;
    sku_suffix: string;
    price: string;
    stock_quantity: string;
    description: string;
    is_default: boolean;
    sort_order: number;
    images: File[];
    existing_images?: string[];
}

interface VariantManagerProps {
    variants: Variant[];
    baseSku: string;
    onVariantsChange: (variants: Variant[]) => void;
    validationErrors?: Record<string, boolean>;
    uploadLimits: {
        maxFileSize: string;
        maxFileSizeBytes: number;
        maxFilesPerVariant: number;
    };
}

export function VariantManager({ variants, baseSku, onVariantsChange, validationErrors = {}, uploadLimits }: VariantManagerProps) {
    const [previews, setPreviews] = useState<Record<number, string[]>>({});

    const addVariant = () => {
        const newVariant: Variant = {
            name: '',
            sku_suffix: '',
            price: '',
            stock_quantity: '',
            description: '',
            is_default: variants.length === 0,
            sort_order: variants.length,
            images: [],
        };
        onVariantsChange([...variants, newVariant]);
    };

    const updateVariant = (index: number, updates: Partial<Variant>) => {
        const updated = [...variants];
        updated[index] = { ...updated[index], ...updates };

        // Ensure only one default
        if (updates.is_default) {
            updated.forEach((v, i) => {
                if (i !== index) v.is_default = false;
            });
        }

        onVariantsChange(updated);
    };

    const removeVariant = (index: number) => {
        if (variants.length <= 1) {
            alert('At least one variant is required');
            return;
        }

        const updated = variants.filter((_, i) => i !== index);

        // Ensure at least one default
        if (!updated.some((v) => v.is_default)) {
            updated[0].is_default = true;
        }

        onVariantsChange(updated);
    };

    const handleImageChange = (variantIndex: number, files: FileList | null) => {
        if (!files) return;

        const newFiles = Array.from(files);
        const currentImages = variants[variantIndex].images;
        const totalImages = currentImages.length + newFiles.length;

        if (totalImages > uploadLimits.maxFilesPerVariant) {
            alert(`Maximum ${uploadLimits.maxFilesPerVariant} images per variant`);
            return;
        }

        updateVariant(variantIndex, { images: [...currentImages, ...newFiles] });

        // Create previews
        const newPreviews = newFiles.map((f) => URL.createObjectURL(f));
        setPreviews((prev) => ({
            ...prev,
            [variantIndex]: [...(prev[variantIndex] || []), ...newPreviews],
        }));
    };

    const removeImage = (variantIndex: number, imageIndex: number, isExisting: boolean) => {
        if (isExisting) {
            const variant = variants[variantIndex];
            const updatedExisting = variant.existing_images?.filter((_, i) => i !== imageIndex) || [];
            updateVariant(variantIndex, { existing_images: updatedExisting });
            return;
        }

        const variant = variants[variantIndex];
        const updatedImages = variant.images.filter((_, i) => i !== imageIndex);
        updateVariant(variantIndex, { images: updatedImages });

        setPreviews((prev) => ({
            ...prev,
            [variantIndex]: prev[variantIndex]?.filter((_, i) => i !== imageIndex) || [],
        }));
    };

    return (
        <Card>
            <CardHeader className="flex flex-row items-center justify-between">
                <CardTitle>Product Variants</CardTitle>
                <Button type="button" onClick={addVariant} size="sm">
                    <Plus className="mr-2 h-4 w-4" />
                    Add Variant
                </Button>
            </CardHeader>
            <CardContent className="space-y-4">
                {variants.map((variant, index) => (
                    <Card key={index} className={variant.is_default ? 'border-blue-500' : ''}>
                        <CardContent className="space-y-4 pt-4">
                            <div className="flex items-start gap-2">
                                <GripVertical className="mt-2 h-5 w-5 cursor-move text-gray-400" />

                                <div className="grid flex-1 grid-cols-1 gap-4 md:grid-cols-2">
                                    {/* Name */}
                                    <div>
                                        <Label>Variant Name *</Label>
                                        <Input
                                            value={variant.name}
                                            onChange={(e) => updateVariant(index, { name: e.target.value })}
                                            placeholder="e.g. Standard, Premium"
                                            className={validationErrors[`variant_${index}_name`] ? 'border-red-500' : ''}
                                        />
                                        {validationErrors[`variant_${index}_name`] && (
                                            <p className="mt-1 text-sm text-red-500">Variant name is required</p>
                                        )}
                                    </div>

                                    {/* SKU */}
                                    <div>
                                        <Label>SKU</Label>
                                        <div className="flex items-center gap-2">
                                            <span className="text-sm text-gray-500">{baseSku || 'BASE'}</span>
                                            <Input
                                                value={variant.sku_suffix}
                                                onChange={(e) => updateVariant(index, { sku_suffix: e.target.value })}
                                                placeholder="-RUG"
                                                className="flex-1"
                                            />
                                        </div>
                                    </div>

                                    {/* Price */}
                                    <div>
                                        <Label>Price *</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            value={variant.price}
                                            onChange={(e) => updateVariant(index, { price: e.target.value })}
                                            placeholder="0.00"
                                            className={validationErrors[`variant_${index}_price`] ? 'border-red-500' : ''}
                                        />
                                        {validationErrors[`variant_${index}_price`] && (
                                            <p className="mt-1 text-sm text-red-500">Price is required</p>
                                        )}
                                    </div>

                                    {/* Stock */}
                                    <div>
                                        <Label>Stock Quantity *</Label>
                                        <Input
                                            type="number"
                                            value={variant.stock_quantity}
                                            onChange={(e) => updateVariant(index, { stock_quantity: e.target.value })}
                                            placeholder="0"
                                            className={validationErrors[`variant_${index}_stock`] ? 'border-red-500' : ''}
                                        />
                                        {validationErrors[`variant_${index}_stock`] && (
                                            <p className="mt-1 text-sm text-red-500">Stock quantity is required</p>
                                        )}
                                    </div>

                                    {/* Description */}
                                    <div className="md:col-span-2">
                                        <Label>Description</Label>
                                        <Textarea
                                            value={variant.description}
                                            onChange={(e) => updateVariant(index, { description: e.target.value })}
                                            placeholder="Specific description for this variant"
                                            rows={3}
                                        />
                                    </div>

                                    {/* Images */}
                                    <div className="md:col-span-2">
                                        <Label>Images (max {uploadLimits.maxFilesPerVariant})</Label>
                                        <Input
                                            type="file"
                                            multiple
                                            accept="image/*"
                                            onChange={(e) => handleImageChange(index, e.target.files)}
                                            disabled={variant.images.length >= uploadLimits.maxFilesPerVariant}
                                        />

                                        {/* Preview existing + new images */}
                                        <div className="mt-2 flex flex-wrap gap-2">
                                            {variant.existing_images?.map((img, imgIndex) => (
                                                <div key={`existing-${imgIndex}`} className="relative h-20 w-20">
                                                    <img src={img} alt="" className="h-full w-full rounded object-cover" />
                                                    <button
                                                        type="button"
                                                        onClick={() => removeImage(index, imgIndex, true)}
                                                        className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            ))}
                                            {previews[index]?.map((preview, imgIndex) => (
                                                <div key={`new-${imgIndex}`} className="relative h-20 w-20">
                                                    <img src={preview} alt="" className="h-full w-full rounded object-cover" />
                                                    <button
                                                        type="button"
                                                        onClick={() => removeImage(index, imgIndex, false)}
                                                        className="absolute -top-2 -right-2 rounded-full bg-red-500 p-1 text-white"
                                                    >
                                                        <X className="h-3 w-3" />
                                                    </button>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                </div>

                                {/* Actions */}
                                <div className="flex flex-col gap-2">
                                    <Button
                                        type="button"
                                        variant={variant.is_default ? 'default' : 'outline'}
                                        size="sm"
                                        onClick={() => updateVariant(index, { is_default: true })}
                                    >
                                        <Star className={`mr-2 h-4 w-4 ${variant.is_default ? 'fill-current' : ''}`} />
                                        {variant.is_default ? 'Default' : 'Set Default'}
                                    </Button>

                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => removeVariant(index)}
                                        disabled={variants.length <= 1}
                                    >
                                        <Trash2 className="mr-2 h-4 w-4" />
                                        Delete
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </CardContent>
        </Card>
    );
}

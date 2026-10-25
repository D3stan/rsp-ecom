import { SafetyInformationField } from '@/components/admin/SafetyInformationField';
import { VariantManager } from '@/components/admin/VariantManager';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router, useForm } from '@inertiajs/react';
import { ChevronDown, Package, Settings, Star } from 'lucide-react';
import { useEffect, useState } from 'react';
import { route } from 'ziggy-js';

interface Category {
    id: number;
    name: string;
}

interface Size {
    id: number;
    name: string;
}

interface ProductVariant {
    id: number;
    name: string;
    sku_suffix: string;
    price: number;
    stock_quantity: number;
    description: string | null;
    images: string[];
    image_urls: string[];
    is_default: boolean;
    sort_order: number;
    status: string;
}

interface Product {
    id: number;
    name: string;
    slug: string;
    base_sku: string;
    status: string;
    featured: boolean;
    category_id: number;
    size_id: number;
    seo_title: string | null;
    seo_description: string | null;
    safety_information: string | null;
    variants: ProductVariant[];
}

interface Props {
    product: Product;
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

export default function EditProduct({ product, categories, sizes, uploadLimits }: Props) {
    const { t } = useTranslation();
    const [isAdvancedOpen, setIsAdvancedOpen] = useState(false);
    const [deletedVariantIds, setDeletedVariantIds] = useState<number[]>([]);

    // Transform product variants into form format
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const transformVariants = (variants: ProductVariant[]): any[] => {
        return variants.map((v) => ({
            id: v.id,
            name: v.name,
            sku_suffix: v.sku_suffix,
            price: v.price.toString(),
            stock_quantity: v.stock_quantity.toString(),
            // The stock as loaded: the server applies (typed - loaded) so orders placed meanwhile are kept
            stock_quantity_original: v.stock_quantity,
            description: v.description || '',
            is_default: v.is_default,
            sort_order: v.sort_order,
            images: [],
            existing_images: v.image_urls || [],
        }));
    };

    const { data, setData, processing, errors, setError, clearErrors } = useForm({
        name: product.name,
        category_id: product.category_id.toString(),
        size_id: product.size_id.toString(),
        status: product.status,
        featured: Boolean(product.featured),
        base_sku: product.base_sku || '',
        seo_title: product.seo_title || '',
        seo_description: product.seo_description || '',
        safety_information: product.safety_information || '',
        variants: transformVariants(product.variants),
    });

    const [validationErrors, setValidationErrors] = useState<Record<string, boolean>>({});

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        // Client-side validation with field highlighting
        const clientErrors: Record<string, boolean> = {};

        if (!data.name.trim()) {
            clientErrors['name'] = true;
        }
        if (!data.category_id) {
            clientErrors['category_id'] = true;
        }
        if (!data.size_id) {
            clientErrors['size_id'] = true;
        }

        // Validate variants
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        data.variants.forEach((v: any, index: number) => {
            if (!v.name) clientErrors[`variant_${index}_name`] = true;
            if (!v.price) clientErrors[`variant_${index}_price`] = true;
            if (!v.stock_quantity) clientErrors[`variant_${index}_stock`] = true;
        });

        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        if (data.variants.some((v: any) => !v.name || !v.price || !v.stock_quantity)) {
            setValidationErrors(clientErrors);
            return;
        }

        if (Object.keys(clientErrors).length > 0) {
            setValidationErrors(clientErrors);
            return;
        }

        setValidationErrors({});

        // Check total file size before uploading
        let totalFileSize = 0;
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (data.variants as any[]).forEach((variant: any) => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            variant.images.forEach((file: any) => {
                totalFileSize += file.size || 0;
            });
        });

        console.log('Total file size:', totalFileSize, 'bytes');
        console.log('Max request size:', uploadLimits.maxRequestSizeBytes, 'bytes');

        if (totalFileSize > uploadLimits.maxRequestSizeBytes) {
            alert(
                t('admin.products.error_file_size', {
                    size: (totalFileSize / 1024 / 1024).toFixed(2),
                    max: uploadLimits.maxRequestSize,
                })
            );
            return;
        }

        // Create FormData for file uploads
        const formData = new FormData();
        formData.append('_method', 'PUT');
        formData.append('name', data.name as string);
        formData.append('category_id', data.category_id as string);
        formData.append('size_id', data.size_id as string);
        formData.append('status', data.status as string);
        formData.append('featured', data.featured ? '1' : '0');
        formData.append('base_sku', data.base_sku as string);
        formData.append('seo_title', data.seo_title as string);
        formData.append('seo_description', data.seo_description as string);
        formData.append('safety_information', data.safety_information as string);

        // Add variants as JSON (without images, with existing_images)
        // Note: existing_images contains full URLs, backend will extract filenames using basename()
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const variantsForJson = (data.variants as any[]).map((v) => ({
            id: v.id,
            name: v.name,
            sku_suffix: v.sku_suffix,
            price: v.price,
            stock_quantity: v.stock_quantity,
            stock_quantity_original: v.stock_quantity_original, // undefined (omitted) for new variants
            description: v.description,
            is_default: v.is_default,
            sort_order: v.sort_order,
            existing_images: v.existing_images || [], // Full URLs for display, backend extracts filenames
        }));
        formData.append('variants', JSON.stringify(variantsForJson));

        // Add deleted variant IDs
        if (deletedVariantIds.length > 0) {
            deletedVariantIds.forEach((id) => {
                formData.append('variants_to_delete[]', id.toString());
            });
        }

        // Add new images separately
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        (data.variants as any[]).forEach((variant, index: number) => {
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            variant.images.forEach((file: any) => {
                formData.append(`variant_images_${index}[]`, file);
            });
        });

        // Debug: Log what we're sending
        console.log('FormData contents:');
        for (const [key, value] of formData.entries()) {
            if (value instanceof File) {
                console.log(`${key}: File(name=${value.name}, size=${value.size})`);
            } else {
                console.log(`${key}: ${value}`);
            }
        }

        router.post(route('admin.products.update', product.id), formData, {
            forceFormData: true,
            onError: (errors: Record<string, string>) => {
                console.error('Server errors:', errors);
                // router.post does not feed useForm: replace (not merge) its errors so stale ones disappear
                clearErrors();
                setError(errors);
                // Display error to user
                if (errors.error) {
                    alert(t('admin.products.error_prefix', { message: errors.error }));
                } else if (errors.variants) {
                    alert(t('admin.products.variant_error_prefix', { message: errors.variants }));
                } else if (Object.keys(errors).some((key) => key.endsWith('.stock_quantity_original'))) {
                    // The page was loaded before the stock-delta form existed: the server asks for a reload
                    alert(Object.entries(errors).find(([key]) => key.endsWith('.stock_quantity_original'))?.[1]);
                } else if (!errors.seo_title && !errors.seo_description) {
                    // SEO errors are shown under their field; anything else: the first server message, never "check the console"
                    alert(Object.values(errors)[0] || t('admin.products.error_occurred'));
                }
            },
        });
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const handleVariantsChange = (newVariants: any[]) => {
        // Track deleted variants
        // eslint-disable-next-line @typescript-eslint/no-explicit-any
        const oldVariantIds = (data.variants as any[])
            // eslint-disable-next-line @typescript-eslint/no-explicit-any
            .map((v: any) => v.id)
            .filter((id): id is number => id !== undefined);
        const newVariantIds = newVariants
            .map((v) => v.id)
            .filter((id): id is number => id !== undefined);

        const removedIds = oldVariantIds.filter((id) => !newVariantIds.includes(id));
        if (removedIds.length > 0) {
            setDeletedVariantIds((prev) => [...prev, ...removedIds]);
        }

        setData('variants', newVariants);
    };

    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const formErrors = errors as Record<string, any>;

    // A server error on an SEO field must be visible: open the card that holds them
    useEffect(() => {
        if (formErrors.seo_title || formErrors.seo_description) {
            setIsAdvancedOpen(true);
        }
    }, [formErrors.seo_title, formErrors.seo_description]);

    return (
        <AdminLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.products.title'), href: '/admin/products' },
                { title: t('admin.products.edit_product'), href: route('admin.products.edit', product.id) },
            ]}
        >
            <Head title={t('admin.products.head_edit', { name: product.name })} />

            <div className="space-y-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">{t('admin.products.edit_product')}</h1>
                        <p className="text-gray-500">{t('admin.products.edit_description')}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* General Error Display */}
                    {formErrors.error && (
                        <div className="rounded-md border border-red-200 bg-red-50 p-4">
                            <p className="text-sm text-red-600">{formErrors.error}</p>
                        </div>
                    )}

                    {/* Basic Information */}
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex items-center gap-2">
                                <Package className="h-5 w-5 text-blue-600" />
                                <CardTitle className="text-lg">{t('admin.products.basic_information')}</CardTitle>
                            </div>
                            <CardDescription>{t('admin.products.basic_information_description')}</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="md:col-span-2">
                                    <Label htmlFor="name">{t('admin.products.product_name_required')}</Label>
                                    <Input
                                        id="name"
                                        value={data.name as string}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder={t('admin.products.placeholder_name')}
                                        className={formErrors.name ? 'border-red-500' : ''}
                                    />
                                    {formErrors.name && <p className="mt-1 text-sm text-red-500">{formErrors.name}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="category_id">{t('admin.products.category_required')}</Label>
                                    <Select
                                        value={data.category_id as string}
                                        onValueChange={(value) => setData('category_id', value)}
                                    >
                                        <SelectTrigger className={formErrors.category_id ? 'border-red-500' : ''}>
                                            <SelectValue placeholder={t('admin.products.select_category')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {categories.map((category) => (
                                                <SelectItem key={category.id} value={category.id.toString()}>
                                                    {category.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {formErrors.category_id && <p className="mt-1 text-sm text-red-500">{formErrors.category_id}</p>}
                                </div>

                                <div>
                                    <Label htmlFor="size_id">{t('admin.products.size_required')}</Label>
                                    <Select value={data.size_id as string} onValueChange={(value) => setData('size_id', value)}>
                                        <SelectTrigger className={formErrors.size_id ? 'border-red-500' : ''}>
                                            <SelectValue placeholder={t('admin.products.select_size')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {sizes.map((size) => (
                                                <SelectItem key={size.id} value={size.id.toString()}>
                                                    {size.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {formErrors.size_id && <p className="mt-1 text-sm text-red-500">{formErrors.size_id}</p>}
                                </div>

                                <div className="md:col-span-2">
                                    <Label htmlFor="base_sku">{t('admin.products.base_sku')}</Label>
                                    <Input
                                        id="base_sku"
                                        value={data.base_sku as string}
                                        onChange={(e) => setData('base_sku', e.target.value)}
                                        placeholder={t('admin.products.placeholder_base_sku')}
                                        className={formErrors.base_sku ? 'border-red-500' : ''}
                                    />
                                    {formErrors.base_sku && <p className="mt-1 text-sm text-red-500">{formErrors.base_sku}</p>}
                                    <p className="mt-1 text-xs text-gray-500">{t('admin.products.base_sku_help')}</p>
                                </div>

                                <div>
                                    <Label htmlFor="status">{t('admin.products.status')}</Label>
                                    <Select value={data.status as string} onValueChange={(value) => setData('status', value)}>
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="active">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-2 rounded-full bg-green-500"></div>
                                                    {t('admin.products.status_active')}
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="inactive">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-2 rounded-full bg-gray-500"></div>
                                                    {t('admin.products.status_inactive')}
                                                </div>
                                            </SelectItem>
                                            <SelectItem value="draft">
                                                <div className="flex items-center gap-2">
                                                    <div className="h-2 w-2 rounded-full bg-yellow-500"></div>
                                                    {t('admin.products.status_draft')}
                                                </div>
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                <div className="flex items-center space-x-2">
                                    <Checkbox
                                        id="featured"
                                        checked={data.featured as boolean}
                                        onCheckedChange={(checked) => setData('featured', Boolean(checked))}
                                    />
                                    <Label htmlFor="featured" className="flex cursor-pointer items-center gap-2">
                                        <Star className="h-4 w-4 text-yellow-500" />
                                        {t('admin.products.featured_product')}
                                    </Label>
                                </div>

                                <SafetyInformationField
                                    value={data.safety_information as string}
                                    onChange={(value) => setData('safety_information', value)}
                                    error={formErrors.safety_information}
                                />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Variants */}
                    <VariantManager
                        variants={data.variants as []}
                        baseSku={(data.base_sku as string) || ''}
                        onVariantsChange={handleVariantsChange}
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
                                            <CardTitle className="text-lg">{t('admin.products.advanced_options')}</CardTitle>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${isAdvancedOpen ? 'rotate-180' : ''}`} />
                                    </div>
                                    <CardDescription>{t('admin.products.advanced_options_description_edit')}</CardDescription>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="pt-0">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="md:col-span-2">
                                            <Label htmlFor="seo_title">{t('admin.products.seo_title')}</Label>
                                            <Input
                                                id="seo_title"
                                                value={(data.seo_title as string) || ''}
                                                onChange={(e) => setData('seo_title', e.target.value)}
                                                placeholder={t('admin.products.placeholder_seo_title')}
                                                className={formErrors.seo_title ? 'border-red-500' : ''}
                                            />
                                            {formErrors.seo_title && <p className="mt-1 text-sm text-red-500">{formErrors.seo_title}</p>}
                                        </div>

                                        <div className="md:col-span-2">
                                            <Label htmlFor="seo_description">{t('admin.products.seo_description')}</Label>
                                            <Textarea
                                                id="seo_description"
                                                value={(data.seo_description as string) || ''}
                                                onChange={(e) => setData('seo_description', e.target.value)}
                                                placeholder={t('admin.products.placeholder_seo_description')}
                                                rows={3}
                                                className={formErrors.seo_description ? 'border-red-500' : ''}
                                            />
                                            {formErrors.seo_description && <p className="mt-1 text-sm text-red-500">{formErrors.seo_description}</p>}
                                            <p
                                                className={`mt-1 text-xs ${((data.seo_description as string) || '').length > 500 ? 'text-red-500' : ((data.seo_description as string) || '').length > 160 ? 'text-amber-600' : 'text-gray-500'}`}
                                            >
                                                {t('admin.products.seo_description_counter', { count: ((data.seo_description as string) || '').length })}
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Submit Button */}
                    <div className="flex flex-col gap-3 pt-4 sm:flex-row">
                        <Button type="submit" disabled={processing} className="order-2 w-full sm:order-1 sm:w-auto">
                            {processing ? t('admin.products.updating') : t('admin.products.update_product')}
                        </Button>
                        <Button
                            type="button"
                            variant="destructive"
                            onClick={() => window.history.back()}
                            className="order-1 w-full sm:order-2 sm:w-auto"
                        >
                            {t('admin.products.cancel')}
                        </Button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

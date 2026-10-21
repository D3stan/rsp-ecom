import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { cn } from '@/lib/utils';
import { Head } from '@inertiajs/react';
import {
    AlertCircle,
    Clock,
    Info,
    Loader2,
    MapPin,
    Package,
    Plus,
    Receipt,
    Scale,
    Shield,
    Sparkles,
    Trash2,
    Truck,
    Wallet,
} from 'lucide-react';
import { FormEvent, useState } from 'react';

export interface AddressInput {
    postal_code: string;
    city: string;
    province: string;
    country: string;
    name?: string;
    street?: string;
}

export interface CarrierOption {
    code: string;
    name: string;
}

export interface RateQuote {
    provider: string;
    carrier_code: string;
    carrier_name: string;
    raw_carrier_code?: string;
    service_name: string;
    net_price?: number;
    total_price: number;
    list_price?: number | null;
    currency?: string;
    taxable_weight_kg?: number | null;
    dimensional_weight_applied?: boolean;
    estimated_delivery?: string | null;
    quote_token?: string | null;
    surcharges?: Record<string, unknown>;
    warnings?: string[];
    margin_delta?: number;
    [key: string]: unknown;
}

export interface QuoteParcel {
    weight: string;
    length: string;
    width: string;
    height: string;
    quantity: number;
}

interface Props {
    sender: AddressInput;
    carriers?: CarrierOption[];
    rate_drivers?: string[];
    default_parcel_weight_kg?: number;
}

export interface DriverBalance {
    balance?: number;
    amount?: number;
    currency?: string;
    [key: string]: unknown;
}

interface CalculateResponse {
    quotes: RateQuote[];
    balances?: Record<string, DriverBalance | number | null | undefined>;
    errors?: Record<string, string>;
}

export default function ShippingQuotes({
    sender,
    rate_drivers = [],
    default_parcel_weight_kg = 1.0,
}: Props) {
    const { t } = useTranslation();

    const [senderAddress, setSenderAddress] = useState<AddressInput>({
        postal_code: sender?.postal_code ?? '',
        city: sender?.city ?? '',
        province: sender?.province ?? '',
        country: sender?.country ?? 'IT',
        name: sender?.name ?? '',
        street: sender?.street ?? '',
    });

    const [recipientAddress, setRecipientAddress] = useState<AddressInput>({
        postal_code: '',
        city: '',
        province: '',
        country: 'IT',
        name: '',
        street: '',
    });

    const [parcels, setParcels] = useState<QuoteParcel[]>([
        {
            weight: default_parcel_weight_kg ? String(default_parcel_weight_kg) : '1',
            length: '',
            width: '',
            height: '',
            quantity: 1,
        },
    ]);

    const [insuranceAmount, setInsuranceAmount] = useState<string>('');
    const [codAmount, setCodAmount] = useState<string>('');
    const [coupon, setCoupon] = useState<string>('');
    const [contentDescription, setContentDescription] = useState<string>('');
    const [selectedDrivers, setSelectedDrivers] = useState<string[]>(rate_drivers);

    const [calculating, setCalculating] = useState<boolean>(false);
    const [calculationError, setCalculationError] = useState<string | null>(null);
    const [results, setResults] = useState<CalculateResponse | null>(null);

    const getCsrfToken = (): string => {
        const xsrfCookie = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        if (xsrfCookie && xsrfCookie[1]) {
            return decodeURIComponent(xsrfCookie[1]);
        }
        const metaTag = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
        return metaTag?.content ?? '';
    };

    const addParcel = () => {
        setParcels((prev) => [
            ...prev,
            {
                weight: default_parcel_weight_kg ? String(default_parcel_weight_kg) : '1',
                length: '',
                width: '',
                height: '',
                quantity: 1,
            },
        ]);
    };

    const removeParcel = (index: number) => {
        setParcels((prev) => prev.filter((_, i) => i !== index));
    };

    const updateParcel = (index: number, field: keyof QuoteParcel, value: string | number) => {
        setParcels((prev) =>
            prev.map((parcel, i) => (i === index ? { ...parcel, [field]: value } : parcel)),
        );
    };

    const toggleDriver = (driver: string) => {
        setSelectedDrivers((prev) =>
            prev.includes(driver) ? prev.filter((d) => d !== driver) : [...prev, driver],
        );
    };

    const toggleAllDrivers = () => {
        if (selectedDrivers.length === rate_drivers.length) {
            setSelectedDrivers([]);
        } else {
            setSelectedDrivers([...rate_drivers]);
        }
    };

    const handleCalculate = async (e: FormEvent) => {
        e.preventDefault();
        setCalculating(true);
        setCalculationError(null);

        try {
            const payload = {
                sender: senderAddress,
                recipient: recipientAddress,
                parcels: parcels.map((p) => ({
                    weight: p.weight ? parseFloat(p.weight) : default_parcel_weight_kg,
                    length: p.length ? parseFloat(p.length) : null,
                    width: p.width ? parseFloat(p.width) : null,
                    height: p.height ? parseFloat(p.height) : null,
                    quantity: p.quantity > 0 ? p.quantity : 1,
                })),
                insurance_amount: insuranceAmount ? parseFloat(insuranceAmount) : null,
                cod_amount: codAmount ? parseFloat(codAmount) : null,
                coupon: coupon.trim() || null,
                content_description: contentDescription.trim() || null,
                drivers: selectedDrivers.length > 0 ? selectedDrivers : null,
            };

            const response = await fetch('/admin/shipping/quotes/calculate', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify(payload),
            });

            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                throw new Error(errData.message || `Request failed with HTTP status ${response.status}`);
            }

            const data = await response.json();
            setResults({
                quotes: Array.isArray(data.quotes) ? data.quotes : [],
                balances: data.balances ?? {},
                errors: data.errors ?? {},
            });
        } catch (err: unknown) {
            const message = err instanceof Error ? err.message : '';
            setCalculationError(
                message ||
                    t('admin.shipping.quotes.error_failed', {
                        defaultValue: 'Failed to calculate quotes. Please verify addresses and try again.',
                    }),
            );
        } finally {
            setCalculating(false);
        }
    };

    const breadcrumbs = [
        { title: t('admin.nav.dashboard', { defaultValue: 'Dashboard' }), href: '/admin/dashboard' },
        { title: t('admin.nav.orders', { defaultValue: 'Orders' }), href: '/admin/orders' },
        {
            title: t('admin.shipping.quotes.title', { defaultValue: 'Shipping Quotes' }),
            href: '/admin/shipping/quotes',
        },
    ];

    const minPrice =
        results && results.quotes.length > 0
            ? Math.min(...results.quotes.map((q) => Number(q.total_price)))
            : null;

    return (
        <AdminLayout breadcrumbs={breadcrumbs}>
            <Head title={t('admin.shipping.quotes.title', { defaultValue: 'Shipping Rate Simulator' })} />

            <div className="mx-auto max-w-7xl space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">
                        {t('admin.shipping.quotes.heading', { defaultValue: 'Shipping Quote Simulator' })}
                    </h1>
                    <p className="text-sm text-muted-foreground mt-1">
                        {t('admin.shipping.quotes.description', {
                            defaultValue:
                                'Compare live rates, transit times, and wallet balances across active shipping providers.',
                        })}
                    </p>
                </div>

                <form onSubmit={handleCalculate} className="space-y-6">
                    <div className="grid gap-6 lg:grid-cols-12">
                        {/* Left Column: Form Controls */}
                        <div className="space-y-6 lg:col-span-6">
                            {/* Address Cards */}
                            <div className="grid gap-4 sm:grid-cols-2">
                                {/* Sender Address */}
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <MapPin className="h-4 w-4 text-primary" />
                                            {t('admin.shipping.quotes.sender_address', { defaultValue: 'Sender (Origin)' })}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2.5 text-xs">
                                        <div className="space-y-1">
                                            <Label htmlFor="sender_postal_code" className="text-xs">
                                                {t('admin.shipping.quotes.postal_code', { defaultValue: 'Postal Code' })} *
                                            </Label>
                                            <Input
                                                id="sender_postal_code"
                                                required
                                                value={senderAddress.postal_code}
                                                onChange={(e) =>
                                                    setSenderAddress((prev) => ({ ...prev, postal_code: e.target.value }))
                                                }
                                                placeholder="e.g. 00100"
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="space-y-1">
                                                <Label htmlFor="sender_city" className="text-xs">
                                                    {t('admin.shipping.quotes.city', { defaultValue: 'City' })} *
                                                </Label>
                                                <Input
                                                    id="sender_city"
                                                    required
                                                    value={senderAddress.city}
                                                    onChange={(e) =>
                                                        setSenderAddress((prev) => ({ ...prev, city: e.target.value }))
                                                    }
                                                    placeholder="City"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label htmlFor="sender_province" className="text-xs">
                                                    {t('admin.shipping.quotes.province', { defaultValue: 'Province' })} *
                                                </Label>
                                                <Input
                                                    id="sender_province"
                                                    required
                                                    maxLength={5}
                                                    value={senderAddress.province}
                                                    onChange={(e) =>
                                                        setSenderAddress((prev) => ({
                                                            ...prev,
                                                            province: e.target.value.toUpperCase(),
                                                        }))
                                                    }
                                                    placeholder="RM"
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="sender_country" className="text-xs">
                                                {t('admin.shipping.quotes.country', { defaultValue: 'Country (ISO)' })} *
                                            </Label>
                                            <Input
                                                id="sender_country"
                                                required
                                                maxLength={2}
                                                value={senderAddress.country}
                                                onChange={(e) =>
                                                    setSenderAddress((prev) => ({
                                                        ...prev,
                                                        country: e.target.value.toUpperCase(),
                                                    }))
                                                }
                                                placeholder="IT"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* Recipient Address */}
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <MapPin className="h-4 w-4 text-emerald-600" />
                                            {t('admin.shipping.quotes.recipient_address', { defaultValue: 'Recipient (Destination)' })}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="space-y-2.5 text-xs">
                                        <div className="space-y-1">
                                            <Label htmlFor="recipient_postal_code" className="text-xs">
                                                {t('admin.shipping.quotes.postal_code', { defaultValue: 'Postal Code' })} *
                                            </Label>
                                            <Input
                                                id="recipient_postal_code"
                                                required
                                                value={recipientAddress.postal_code}
                                                onChange={(e) =>
                                                    setRecipientAddress((prev) => ({ ...prev, postal_code: e.target.value }))
                                                }
                                                placeholder="e.g. 20121"
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-2">
                                            <div className="space-y-1">
                                                <Label htmlFor="recipient_city" className="text-xs">
                                                    {t('admin.shipping.quotes.city', { defaultValue: 'City' })} *
                                                </Label>
                                                <Input
                                                    id="recipient_city"
                                                    required
                                                    value={recipientAddress.city}
                                                    onChange={(e) =>
                                                        setRecipientAddress((prev) => ({ ...prev, city: e.target.value }))
                                                    }
                                                    placeholder="City"
                                                />
                                            </div>
                                            <div className="space-y-1">
                                                <Label htmlFor="recipient_province" className="text-xs">
                                                    {t('admin.shipping.quotes.province', { defaultValue: 'Province' })} *
                                                </Label>
                                                <Input
                                                    id="recipient_province"
                                                    required
                                                    maxLength={5}
                                                    value={recipientAddress.province}
                                                    onChange={(e) =>
                                                        setRecipientAddress((prev) => ({
                                                            ...prev,
                                                            province: e.target.value.toUpperCase(),
                                                        }))
                                                    }
                                                    placeholder="MI"
                                                />
                                            </div>
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="recipient_country" className="text-xs">
                                                {t('admin.shipping.quotes.country', { defaultValue: 'Country (ISO)' })} *
                                            </Label>
                                            <Input
                                                id="recipient_country"
                                                required
                                                maxLength={2}
                                                value={recipientAddress.country}
                                                onChange={(e) =>
                                                    setRecipientAddress((prev) => ({
                                                        ...prev,
                                                        country: e.target.value.toUpperCase(),
                                                    }))
                                                }
                                                placeholder="IT"
                                            />
                                        </div>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Multi-Parcel Editor */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Package className="h-4 w-4" />
                                            {t('admin.shipping.quotes.parcels', { defaultValue: 'Parcels' })}
                                        </CardTitle>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            className="h-7 text-xs"
                                            onClick={addParcel}
                                        >
                                            <Plus className="mr-1 h-3.5 w-3.5" />
                                            {t('admin.shipping.quotes.add_parcel', { defaultValue: 'Add Parcel' })}
                                        </Button>
                                    </div>
                                    <CardDescription className="text-xs">
                                        {t('admin.shipping.quotes.parcels_help', {
                                            defaultValue: 'Enter weight and dimensions (LxWxH in cm) to compute volumetric rates.',
                                        })}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {parcels.map((parcel, idx) => (
                                        <div
                                            key={idx}
                                            className="grid grid-cols-2 gap-2 rounded-md border p-3 sm:grid-cols-6 items-end"
                                        >
                                            <div className="space-y-1 sm:col-span-1">
                                                <Label htmlFor={`p_weight_${idx}`} className="text-[11px]">
                                                    {t('admin.shipping.quotes.weight', { defaultValue: 'Weight (kg)' })} *
                                                </Label>
                                                <Input
                                                    id={`p_weight_${idx}`}
                                                    type="number"
                                                    step="0.01"
                                                    min="0.01"
                                                    required
                                                    value={parcel.weight}
                                                    onChange={(e) => updateParcel(idx, 'weight', e.target.value)}
                                                    placeholder="1.0"
                                                />
                                            </div>
                                            <div className="space-y-1 sm:col-span-1">
                                                <Label htmlFor={`p_length_${idx}`} className="text-[11px]">
                                                    {t('admin.shipping.quotes.length', { defaultValue: 'L (cm)' })}
                                                </Label>
                                                <Input
                                                    id={`p_length_${idx}`}
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    value={parcel.length}
                                                    onChange={(e) => updateParcel(idx, 'length', e.target.value)}
                                                    placeholder="L"
                                                />
                                            </div>
                                            <div className="space-y-1 sm:col-span-1">
                                                <Label htmlFor={`p_width_${idx}`} className="text-[11px]">
                                                    {t('admin.shipping.quotes.width', { defaultValue: 'W (cm)' })}
                                                </Label>
                                                <Input
                                                    id={`p_width_${idx}`}
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    value={parcel.width}
                                                    onChange={(e) => updateParcel(idx, 'width', e.target.value)}
                                                    placeholder="W"
                                                />
                                            </div>
                                            <div className="space-y-1 sm:col-span-1">
                                                <Label htmlFor={`p_height_${idx}`} className="text-[11px]">
                                                    {t('admin.shipping.quotes.height', { defaultValue: 'H (cm)' })}
                                                </Label>
                                                <Input
                                                    id={`p_height_${idx}`}
                                                    type="number"
                                                    step="0.1"
                                                    min="0"
                                                    value={parcel.height}
                                                    onChange={(e) => updateParcel(idx, 'height', e.target.value)}
                                                    placeholder="H"
                                                />
                                            </div>
                                            <div className="space-y-1 sm:col-span-1">
                                                <Label htmlFor={`p_qty_${idx}`} className="text-[11px]">
                                                    {t('admin.shipping.quotes.quantity', { defaultValue: 'Qty' })}
                                                </Label>
                                                <Input
                                                    id={`p_qty_${idx}`}
                                                    type="number"
                                                    min="1"
                                                    value={parcel.quantity}
                                                    onChange={(e) =>
                                                        updateParcel(idx, 'quantity', parseInt(e.target.value, 10) || 1)
                                                    }
                                                />
                                            </div>
                                            <div className="flex justify-end sm:col-span-1">
                                                {parcels.length > 1 ? (
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-9 w-9 p-0 text-muted-foreground hover:text-destructive"
                                                        onClick={() => removeParcel(idx)}
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                ) : null}
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>

                            {/* Optional Accessories & Provider Filters */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                        <Shield className="h-4 w-4" />
                                        {t('admin.shipping.quotes.accessories_filters', {
                                            defaultValue: 'Accessories & Filters',
                                        })}
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4 text-xs">
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="insurance_amount">
                                                {t('admin.shipping.quotes.insurance', { defaultValue: 'Insurance Amount (€)' })}
                                            </Label>
                                            <Input
                                                id="insurance_amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={insuranceAmount}
                                                onChange={(e) => setInsuranceAmount(e.target.value)}
                                                placeholder="0.00"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="cod_amount">
                                                {t('admin.shipping.quotes.cod', { defaultValue: 'Cash on Delivery (€)' })}
                                            </Label>
                                            <Input
                                                id="cod_amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={codAmount}
                                                onChange={(e) => setCodAmount(e.target.value)}
                                                placeholder="0.00"
                                            />
                                        </div>
                                    </div>

                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <div className="space-y-1">
                                            <Label htmlFor="coupon">
                                                {t('admin.shipping.quotes.coupon', { defaultValue: 'Carrier Coupon / Promo Code' })}
                                            </Label>
                                            <Input
                                                id="coupon"
                                                value={coupon}
                                                onChange={(e) => setCoupon(e.target.value)}
                                                placeholder="Optional code"
                                            />
                                        </div>
                                        <div className="space-y-1">
                                            <Label htmlFor="content_description">
                                                {t('admin.shipping.quotes.description_field', {
                                                    defaultValue: 'Content Description',
                                                })}
                                            </Label>
                                            <Input
                                                id="content_description"
                                                value={contentDescription}
                                                onChange={(e) => setContentDescription(e.target.value)}
                                                placeholder="e.g. Merce varia"
                                            />
                                        </div>
                                    </div>

                                    {rate_drivers.length > 0 && (
                                        <div className="space-y-2 border-t pt-3">
                                            <div className="flex items-center justify-between">
                                                <Label className="text-xs font-semibold">
                                                    {t('admin.shipping.quotes.filter_providers', {
                                                        defaultValue: 'Include Rate Providers',
                                                    })}
                                                </Label>
                                                <button
                                                    type="button"
                                                    onClick={toggleAllDrivers}
                                                    className="text-[11px] text-primary hover:underline"
                                                >
                                                    {selectedDrivers.length === rate_drivers.length
                                                        ? t('admin.shipping.quotes.deselect_all', { defaultValue: 'Deselect All' })
                                                        : t('admin.shipping.quotes.select_all', { defaultValue: 'Select All' })}
                                                </button>
                                            </div>
                                            <div className="flex flex-wrap gap-3">
                                                {rate_drivers.map((driver) => (
                                                    <div key={driver} className="flex items-center gap-1.5">
                                                        <Checkbox
                                                            id={`driver_${driver}`}
                                                            checked={selectedDrivers.includes(driver)}
                                                            onCheckedChange={() => toggleDriver(driver)}
                                                        />
                                                        <Label
                                                            htmlFor={`driver_${driver}`}
                                                            className="text-xs font-medium capitalize cursor-pointer"
                                                        >
                                                            {driver}
                                                        </Label>
                                                    </div>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                                <CardFooter>
                                    <Button type="submit" className="w-full" size="lg" disabled={calculating}>
                                        {calculating ? (
                                            <>
                                                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                                {t('admin.shipping.quotes.calculating', { defaultValue: 'Calculating Quotes...' })}
                                            </>
                                        ) : (
                                            <>
                                                <Scale className="mr-2 h-4 w-4" />
                                                {t('admin.shipping.quotes.calculate_button', {
                                                    defaultValue: 'Calculate Live Quotes',
                                                })}
                                            </>
                                        )}
                                    </Button>
                                </CardFooter>
                            </Card>
                        </div>

                        {/* Right Column: Quotes & Balances Results */}
                        <div className="space-y-6 lg:col-span-6">
                            {calculationError && (
                                <Alert variant="destructive">
                                    <AlertCircle className="h-4 w-4" />
                                    <AlertTitle>
                                        {t('admin.shipping.quotes.calculation_failed', { defaultValue: 'Calculation Error' })}
                                    </AlertTitle>
                                    <AlertDescription>{calculationError}</AlertDescription>
                                </Alert>
                            )}

                            {/* Wallet Balances Card */}
                            {results && results.balances && Object.keys(results.balances).length > 0 && (
                                <Card className="border-primary/20 bg-primary/5">
                                    <CardHeader className="py-3">
                                        <CardTitle className="flex items-center gap-2 text-sm font-semibold">
                                            <Wallet className="h-4 w-4 text-primary" />
                                            {t('admin.shipping.quotes.prepaid_balances', { defaultValue: 'Prepaid Wallet Balances' })}
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent className="pb-3 pt-0">
                                        <div className="flex flex-wrap gap-2">
                                            {Object.entries(results.balances).map(([provider, b]) => {
                                                const amount =
                                                    typeof b === 'object' && b !== null
                                                        ? (b.balance ?? b.amount ?? 0)
                                                        : Number(b);
                                                const curr =
                                                    typeof b === 'object' && b !== null && b.currency
                                                        ? (b.currency === 'EUR' ? '€' : b.currency)
                                                        : '€';
                                                return (
                                                    <div
                                                        key={provider}
                                                        className="inline-flex items-center gap-1.5 rounded-md border bg-background px-3 py-1.5 text-xs shadow-xs"
                                                    >
                                                        <span className="font-semibold capitalize">{provider}:</span>
                                                        <span className="font-bold text-foreground">
                                                            {curr}{Number(amount).toFixed(2)}
                                                        </span>
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    </CardContent>
                                </Card>
                            )}

                            {/* Provider Warning/Error Banners */}
                            {results && results.errors && Object.keys(results.errors).length > 0 && (
                                <div className="space-y-2">
                                    {Object.entries(results.errors).map(([provider, err]) => (
                                        <div
                                            key={provider}
                                            className="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-800 dark:text-amber-300"
                                        >
                                            <AlertCircle className="h-4 w-4 shrink-0 mt-0.5" />
                                            <div>
                                                <span className="font-semibold capitalize">{provider}: </span>
                                                <span>{err}</span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}

                            {/* Quotes Results List */}
                            <Card>
                                <CardHeader className="pb-3">
                                    <div className="flex items-center justify-between">
                                        <CardTitle className="flex items-center gap-2 text-base font-semibold">
                                            <Truck className="h-4 w-4" />
                                            {t('admin.shipping.quotes.results_title', { defaultValue: 'Available Live Quotes' })}
                                        </CardTitle>
                                        {results && (
                                            <Badge variant="secondary">
                                                {t('admin.shipping.quotes.quotes_count', {
                                                    count: results.quotes.length,
                                                    defaultValue: `${results.quotes.length} Options`,
                                                })}
                                            </Badge>
                                        )}
                                    </div>
                                    <CardDescription className="text-xs">
                                        {t('admin.shipping.quotes.results_help', {
                                            defaultValue:
                                                'Live negotiated carrier rates ranked by total cost, with transit times and volumetric adjustments.',
                                        })}
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-3">
                                    {calculating ? (
                                        <div className="py-12 text-center text-muted-foreground space-y-2">
                                            <Loader2 className="mx-auto h-8 w-8 animate-spin text-primary" />
                                            <p className="text-sm">
                                                {t('admin.shipping.quotes.querying_providers', {
                                                    defaultValue: 'Querying external carrier quote APIs...',
                                                })}
                                            </p>
                                        </div>
                                    ) : !results ? (
                                        <div className="py-12 text-center text-muted-foreground space-y-2">
                                            <Scale className="mx-auto h-8 w-8 opacity-40" />
                                            <p className="text-sm">
                                                {t('admin.shipping.quotes.empty_state', {
                                                    defaultValue:
                                                        'Enter origin, destination and parcel weights, then click "Calculate Live Quotes".',
                                                })}
                                            </p>
                                        </div>
                                    ) : results.quotes.length === 0 ? (
                                        <div className="py-10 text-center text-muted-foreground space-y-2">
                                            <Info className="mx-auto h-8 w-8 opacity-50" />
                                            <p className="text-sm font-medium">
                                                {t('admin.shipping.quotes.no_rates_found', {
                                                    defaultValue: 'No quotes available for this route and weight configuration.',
                                                })}
                                            </p>
                                        </div>
                                    ) : (
                                        <div className="space-y-3">
                                            {results.quotes.map((quote, idx) => {
                                                const isBestRate = minPrice !== null && Number(quote.total_price) === minPrice;
                                                const hasListSavings =
                                                    quote.list_price !== null &&
                                                    quote.list_price !== undefined &&
                                                    quote.list_price > quote.total_price;
                                                const savingsAmount = hasListSavings
                                                    ? (quote.list_price as number) - quote.total_price
                                                    : 0;

                                                return (
                                                    <div
                                                        key={`${quote.provider}-${quote.carrier_code}-${quote.service_name}-${idx}`}
                                                        className={cn(
                                                            'rounded-lg border p-4 transition-all relative',
                                                            isBestRate
                                                                ? 'border-emerald-500/50 bg-emerald-500/5 shadow-xs ring-1 ring-emerald-500/30'
                                                                : 'bg-card hover:border-muted-foreground/30',
                                                        )}
                                                    >
                                                        {isBestRate && (
                                                            <div className="absolute -top-2.5 right-4">
                                                                <Badge className="bg-emerald-600 text-white hover:bg-emerald-700 shadow-xs text-[10px] uppercase font-bold tracking-wide">
                                                                    <Sparkles className="mr-1 h-3 w-3" />
                                                                    {t('admin.shipping.quotes.best_rate', {
                                                                        defaultValue: 'Best Rate',
                                                                    })}
                                                                </Badge>
                                                            </div>
                                                        )}

                                                        <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                                            <div className="space-y-1.5">
                                                                <div className="flex flex-wrap items-center gap-1.5">
                                                                    <Badge variant="outline" className="text-xs capitalize font-semibold">
                                                                        {quote.provider}
                                                                    </Badge>
                                                                    <span className="font-bold text-sm text-foreground">
                                                                        {quote.carrier_name}
                                                                    </span>
                                                                    {quote.raw_carrier_code && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            ({quote.raw_carrier_code})
                                                                        </span>
                                                                    )}
                                                                </div>

                                                                <p className="text-xs font-medium text-foreground">
                                                                    {quote.service_name}
                                                                </p>

                                                                <div className="flex flex-wrap items-center gap-2 pt-0.5 text-xs text-muted-foreground">
                                                                    {quote.estimated_delivery && (
                                                                        <span className="inline-flex items-center gap-1">
                                                                            <Clock className="h-3.5 w-3.5 text-primary" />
                                                                            {quote.estimated_delivery}
                                                                        </span>
                                                                    )}

                                                                    {quote.taxable_weight_kg !== null &&
                                                                        quote.taxable_weight_kg !== undefined && (
                                                                            <span className="inline-flex items-center gap-1">
                                                                                <Scale className="h-3.5 w-3.5" />
                                                                                {quote.taxable_weight_kg} kg
                                                                            </span>
                                                                        )}

                                                                    {quote.dimensional_weight_applied && (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="border-amber-500/50 bg-amber-500/10 text-amber-700 dark:text-amber-400 text-[10px]"
                                                                        >
                                                                            {t('admin.shipping.quotes.volumetric_weight_applied', {
                                                                                defaultValue: 'Volumetric Weight Applied',
                                                                            })}
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                            </div>

                                                            {/* Price Breakdown */}
                                                            <div className="text-right space-y-1 self-end sm:self-auto">
                                                                <div className="flex items-baseline justify-end gap-1.5">
                                                                    {hasListSavings && (
                                                                        <span className="text-xs text-muted-foreground line-through">
                                                                            €{Number(quote.list_price).toFixed(2)}
                                                                        </span>
                                                                    )}
                                                                    <span className="text-lg font-extrabold text-foreground">
                                                                        €{Number(quote.total_price).toFixed(2)}
                                                                    </span>
                                                                </div>

                                                                <div className="flex flex-wrap items-center justify-end gap-1.5">
                                                                    {quote.net_price !== null && quote.net_price !== undefined && (
                                                                        <span className="text-xs text-muted-foreground">
                                                                            Net: €{Number(quote.net_price).toFixed(2)}
                                                                        </span>
                                                                    )}
                                                                    {hasListSavings && (
                                                                        <Badge
                                                                            variant="outline"
                                                                            className="border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-400 text-[10px] font-semibold"
                                                                        >
                                                                            {t('admin.shipping.quotes.save_amount', {
                                                                                amount: savingsAmount.toFixed(2),
                                                                                defaultValue: `Save €${savingsAmount.toFixed(2)}`,
                                                                            })}
                                                                        </Badge>
                                                                    )}
                                                                </div>
                                                            </div>
                                                        </div>

                                                        {/* Surcharges breakdown */}
                                                        {quote.surcharges && Object.keys(quote.surcharges).length > 0 && (
                                                            <div className="mt-3 rounded-md bg-muted/40 p-2 text-xs space-y-1 border">
                                                                <div className="flex items-center gap-1 text-[11px] font-semibold text-muted-foreground">
                                                                    <Receipt className="h-3 w-3" />
                                                                    <span>
                                                                        {t('admin.shipping.quotes.surcharges', {
                                                                            defaultValue: 'Surcharges Breakdown',
                                                                        })}
                                                                        :
                                                                    </span>
                                                                </div>
                                                                <div className="grid grid-cols-2 gap-x-4 gap-y-0.5 text-[11px]">
                                                                    {Object.entries(quote.surcharges).map(([sName, sVal]) => (
                                                                        <div key={sName} className="flex justify-between">
                                                                            <span className="text-muted-foreground capitalize">
                                                                                {sName.replace(/_/g, ' ')}:
                                                                            </span>
                                                                            <span className="font-medium">
                                                                                {typeof sVal === 'number'
                                                                                    ? `€${sVal.toFixed(2)}`
                                                                                    : String(sVal)}
                                                                            </span>
                                                                        </div>
                                                                    ))}
                                                                </div>
                                                            </div>
                                                        )}

                                                        {/* Warnings */}
                                                        {quote.warnings && quote.warnings.length > 0 && (
                                                            <div className="mt-2 space-y-0.5 text-xs text-amber-600 dark:text-amber-400">
                                                                {quote.warnings.map((w, wIdx) => (
                                                                    <p key={wIdx}>• {w}</p>
                                                                ))}
                                                            </div>
                                                        )}
                                                    </div>
                                                );
                                            })}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        </div>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}

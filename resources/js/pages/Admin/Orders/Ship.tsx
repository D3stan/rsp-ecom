import BuyLabelDialog from '@/components/admin/orders/BuyLabelDialog';
import ShippingIssues, { ShippingIssue } from '@/components/admin/orders/ShippingIssues';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    AlertCircle,
    AlertTriangle,
    ArrowLeft,
    ArrowUpDown,
    ChevronDown,
    Clock,
    Loader2,
    MapPin,
    Package,
    RefreshCw,
    Star,
    Truck,
    Wallet,
} from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

interface Order {
    id: number;
    order_number: string;
    tracking_number?: string | null;
    tracking_carrier?: string | null;
    tracking_driver?: string | null;
    shipping_cost?: number;
    subtotal?: number;
    shippingAddress?: {
        first_name: string;
        last_name: string;
        company?: string;
        address_line_1: string;
        address_line_2?: string;
        city: string;
        state: string;
        postal_code: string;
        country: string;
        phone?: string;
    } | null;
    orderItems?: Array<{
        id: number;
        product: { name: string };
        variant?: { name: string } | null;
        quantity: number;
    }>;
}

interface Carrier {
    code: string;
    name: string;
}

interface Parcel {
    weight?: number | string | null;
    length?: number | string | null;
    width?: number | string | null;
    height?: number | string | null;
    label?: string;
    // index signature so a parcel satisfies Inertia's useForm data constraint
    [key: string]: number | string | null | undefined;
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
    // our carrier code when a label driver can buy this quote (the ship registers it if it is new), else null
    store_carrier_code?: string | null;
    [key: string]: unknown;
}

export interface DriverBalance {
    balance?: number;
    amount?: number;
    currency?: string;
    [key: string]: unknown;
}

interface Rates {
    status: 'idle' | 'loading' | 'ready' | 'error';
    quotes: RateQuote[];
    balances: Record<string, DriverBalance | number | null | undefined>;
    issues: ShippingIssue[];
    message?: string;
}

interface Props {
    order: Order;
    carriers?: Carrier[];
    // false: the shipped email is switched off in the settings
    shipped_email_enabled?: boolean;
    // optional label creation (admin setting, off by default)
    labels_enabled?: boolean;
    // parcels prefilled from the order's box sizes (label mode)
    parcels?: Parcel[];
    label_drivers?: string[];
    // carrier codes recommended first (settings); empty = any
    preferred_carriers?: string[];
    // carrier of the last label bought (kept in view in the rate list)
    last_carrier?: string | null;
    // admin-facing provider names keyed by driver name (from each provider manifest)
    provider_names?: Record<string, string>;
    tracking_drivers?: string[];
    default_tracking_driver?: string;
    shipping_cost?: number;
    sender_configured?: boolean;
    // the next working day (Y-m-d): the pickup date the form starts from
    default_pickup_date?: string;
}

type Tab = 'label' | 'manual';

type ShipForm = {
    tracking_carrier: string;
    carrier_name: string;
    tracking_number: string;
    tracking_driver: string;
    label_driver: string;
    ship_without_tracking: boolean;
    notify_customer: boolean;
    create_label: boolean;
    parcels: Parcel[];
    insurance_amount: string;
    cod_amount: string;
    quote_token: string;
    pickup_date: string;
};

// quotes shown before "show more" when no rate is recommended, starred or last used
const VISIBLE_QUOTES = 3;

// one provider can return several offers for the same courier and service: the offer token (or raw code + price) tells them apart
const quoteKey = (quote: RateQuote) =>
    `${quote.provider}:${quote.raw_carrier_code ?? quote.carrier_code}:${quote.service_name}:${quote.quote_token ?? quote.total_price}`;
const euro = (amount: number) => `€${Number(amount).toFixed(2)}`;
const balanceAmount = (balance: DriverBalance | number | null | undefined) =>
    typeof balance === 'object' && balance !== null ? Number(balance.balance ?? balance.amount ?? 0) : balance == null ? null : Number(balance);

/**
 * Ship page, two ways to ship. "Buy label": parcels prefilled from the box sizes, rates fetched automatically and the
 * cheapest buyable one that the customer's shipping covers preselected; the admin reviews and buys. "Already have a
 * label": a shipment bought elsewhere (a shipping platform's site, a carrier counter) is attached by tracking number.
 * The server (ShippingService) records the shipment, moves the order to "shipped" and sends one shipped email.
 */
export default function OrderShip({
    order,
    carriers = [],
    shipped_email_enabled = true,
    labels_enabled = false,
    parcels = [],
    label_drivers = [],
    preferred_carriers = [],
    last_carrier = null,
    provider_names = {},
    tracking_drivers = [],
    default_tracking_driver = '',
    shipping_cost,
    sender_configured = true,
    default_pickup_date = '',
}: Props) {
    const { t } = useTranslation();
    const [tab, setTab] = useState<Tab>(labels_enabled ? 'label' : 'manual');
    const [confirming, setConfirming] = useState(false);
    const [buying, setBuying] = useState(false);
    const [parcelsOpen, setParcelsOpen] = useState(false);
    const [optionsOpen, setOptionsOpen] = useState(false);
    const [showAllQuotes, setShowAllQuotes] = useState(false);
    const [rates, setRates] = useState<Rates>({ status: 'idle', quotes: [], balances: {}, issues: [] });
    const [selectedKey, setSelectedKey] = useState<string | null>(null);
    const [refreshCount, setRefreshCount] = useState(0);
    const [favourites, setFavourites] = useState<string[]>(preferred_carriers);
    const [sortBy, setSortBy] = useState<'price' | 'provider'>('price');

    const orderValue = Number(order.subtotal ?? 0);
    const customerPaid = Number(order.shipping_cost ?? shipping_cost ?? 0);
    const initialTrackingDriver =
        order.tracking_driver && tracking_drivers.includes(order.tracking_driver)
            ? order.tracking_driver
            : tracking_drivers.includes(default_tracking_driver)
              ? default_tracking_driver
              : '';

    const { data, setData, post, processing, errors } = useForm<ShipForm>({
        // a carrier/number saved earlier on the order is prefilled
        tracking_carrier: carriers.some((carrier) => carrier.code === order.tracking_carrier) ? (order.tracking_carrier as string) : '',
        carrier_name: '',
        tracking_number: order.tracking_number ?? '',
        tracking_driver: initialTrackingDriver,
        label_driver: '',
        ship_without_tracking: false,
        notify_customer: false,
        create_label: labels_enabled,
        parcels: parcels.length > 0 ? parcels : [{ weight: '', length: '', width: '', height: '' }],
        insurance_amount: '',
        cod_amount: '',
        quote_token: '',
        pickup_date: default_pickup_date,
    });

    const getCsrfToken = (): string => {
        const xsrfCookie = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
        if (xsrfCookie && xsrfCookie[1]) {
            return decodeURIComponent(xsrfCookie[1]);
        }
        const metaTag = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
        return metaTag?.content ?? '';
    };

    const selectQuote = (quote: RateQuote) => {
        setSelectedKey(quoteKey(quote));
        setData((prev) => ({
            ...prev,
            label_driver: quote.provider,
            tracking_carrier: quote.store_carrier_code ?? '',
            carrier_name: quote.carrier_name ?? '',
            quote_token: quote.quote_token ?? '',
        }));
    };

    // cheapest buyable rate the customer's shipping covers, from a preferred carrier when one qualifies (quotes arrive sorted by price)
    const recommend = (quotes: RateQuote[]) => {
        const covered = quotes.filter((quote) => quote.store_carrier_code && (quote.margin_delta ?? customerPaid - quote.total_price) >= 0);
        return covered.find((quote) => favourites.includes(quote.store_carrier_code as string)) ?? covered[0] ?? null;
    };
    const buyable = rates.quotes.filter((quote) => quote.store_carrier_code);
    const recommended = recommend(rates.quotes);

    // Rates follow the parcels, insurance and pickup date: any change makes the current ones (and their quote token) stale.
    const quoteInputs = JSON.stringify([data.parcels, data.insurance_amount, data.pickup_date]);
    useEffect(() => {
        if (tab !== 'label' || !sender_configured) {
            return;
        }

        setSelectedKey(null);
        setData('quote_token', '');
        setRates((prev) => ({ ...prev, status: 'loading' }));

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(`/admin/orders/${order.id}/quotes`, {
                    method: 'POST',
                    signal: controller.signal,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({
                        parcels: data.parcels,
                        insurance_amount: data.insurance_amount || null,
                        pickup_date: data.pickup_date || null,
                    }),
                });

                if (!response.ok) {
                    const errData = await response.json().catch(() => ({}));
                    throw new Error(errData.message || `Request failed with status ${response.status}`);
                }

                const result = await response.json();
                const quotes: RateQuote[] = Array.isArray(result.quotes) ? result.quotes : [];
                setRates({ status: 'ready', quotes, balances: result.balances ?? {}, issues: result.issues ?? [] });

                const pick = recommend(quotes);
                if (pick) {
                    selectQuote(pick);
                }
            } catch (err: unknown) {
                if (controller.signal.aborted) return;
                const message = err instanceof Error ? err.message : '';
                setRates({ status: 'error', quotes: [], balances: {}, issues: [], message: message || t('admin.orders.ship.compare_error') });
            }
        }, 500);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [quoteInputs, tab, refreshCount]);

    // the star saves the carrier in the preferred carriers setting (optimistic, restored on failure)
    const toggleFavourite = async (quote: RateQuote) => {
        const code = quote.store_carrier_code as string;
        const previous = favourites;
        setFavourites(previous.includes(code) ? previous.filter((c) => c !== code) : [...previous, code]);
        try {
            const response = await fetch('/admin/shipping/preferred-carriers/toggle', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': getCsrfToken(),
                },
                body: JSON.stringify({ carrier_code: code, carrier_name: quote.carrier_name }),
            });
            if (!response.ok) throw new Error();
            setFavourites((await response.json()).preferred_carriers ?? []);
        } catch {
            setFavourites(previous);
        }
    };

    const switchTab = (next: Tab) => {
        setTab(next);
        setData('create_label', next === 'label');
    };

    const selected = rates.quotes.find((quote) => quoteKey(quote) === selectedKey) ?? null;
    const withoutNumber = data.tracking_number.trim() === '';
    const canSubmit =
        !processing &&
        (tab === 'label'
            ? rates.status === 'ready' && selected !== null && sender_configured && data.pickup_date !== ''
            : carriers.some((carrier) => carrier.code === data.tracking_carrier) && (!withoutNumber || data.ship_without_tracking));

    const send = () =>
        post(`/admin/orders/${order.id}/ship`, {
            preserveScroll: true,
            onFinish: () => {
                setConfirming(false);
                setBuying(false);
            },
        });

    // the earliest date the form accepts: tomorrow, in the admin's own calendar
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    const minPickupDate = `${tomorrow.getFullYear()}-${String(tomorrow.getMonth() + 1).padStart(2, '0')}-${String(tomorrow.getDate()).padStart(2, '0')}`;

    const setParcel = (index: number, field: keyof Parcel, value: string) =>
        setData(
            'parcels',
            data.parcels.map((parcel, i) => (i === index ? { ...parcel, [field]: value } : parcel)),
        );
    const addParcel = () => setData('parcels', [...data.parcels, { weight: '', length: '', width: '', height: '' }]);
    const removeParcel = (index: number) =>
        setData(
            'parcels',
            data.parcels.filter((_, i) => i !== index),
        );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canSubmit) return;

        // buying costs money: confirm price, pickup date and balance first
        if (tab === 'label') {
            setBuying(true);
            return;
        }

        // no tracking number: ask first (the customer gets no tracking link now)
        if (withoutNumber) {
            setConfirming(true);
            return;
        }

        send();
    };

    const emailHint = !shipped_email_enabled
        ? t('admin.orders.ship.email_disabled')
        : tab === 'label'
          ? t('admin.orders.ship.email_on_pickup')
          : !withoutNumber
            ? t('admin.orders.ship.email_with_tracking')
            : data.notify_customer
              ? t('admin.orders.ship.email_now')
              : t('admin.orders.ship.email_later');

    const address = order.shippingAddress;
    const addressWarnings = address
        ? [!address.phone?.trim() && t('admin.orders.ship.missing_phone'), !address.state?.trim() && t('admin.orders.ship.missing_province')].filter(
              Boolean,
          )
        : [];

    const totalWeight = data.parcels.reduce((sum, parcel) => sum + (Number(parcel.weight) || 0), 0);
    const parcelDims = data.parcels
        .map((parcel) => (parcel.length && parcel.width && parcel.height ? `${parcel.length}×${parcel.width}×${parcel.height}` : null))
        .filter(Boolean)
        .join(', ');

    // in view by default: the selected and recommended rates, starred carriers and the last one used; else the cheapest few
    const isFavourite = (quote: RateQuote) => !!quote.store_carrier_code && favourites.includes(quote.store_carrier_code);
    const isLastUsed = (quote: RateQuote) => !!quote.store_carrier_code && quote.store_carrier_code === last_carrier;
    const pinnedQuotes = rates.quotes.filter(
        (quote) =>
            quoteKey(quote) === selectedKey || (recommended && quoteKey(quote) === quoteKey(recommended)) || isFavourite(quote) || isLastUsed(quote),
    );
    // by provider: provider name A→Z, then carrier name, then price (quotes arrive sorted by price)
    const sortedQuotes =
        sortBy === 'price'
            ? rates.quotes
            : [...rates.quotes].sort(
                  (a, b) =>
                      (provider_names[a.provider] ?? a.provider).localeCompare(provider_names[b.provider] ?? b.provider) ||
                      a.carrier_name.localeCompare(b.carrier_name) ||
                      a.total_price - b.total_price,
              );
    const visibleQuotes = showAllQuotes
        ? sortedQuotes
        : sortedQuotes.filter((quote, i) => (pinnedQuotes.length > 0 ? pinnedQuotes.includes(quote) : i < VISIBLE_QUOTES));

    // the selected provider's prepaid balance, shown only when it would cover just a few more labels
    const selectedBalance = selected ? balanceAmount(rates.balances[selected.provider]) : null;
    const balanceState =
        selected && selectedBalance !== null
            ? selectedBalance < selected.total_price
                ? 'insufficient'
                : selectedBalance < selected.total_price * 3
                  ? 'low'
                  : null
            : null;

    const driverName = (driver: string) =>
        driver === 'manual' ? t('admin.orders.ship.bought_direct') : (provider_names[driver] ?? driver.charAt(0).toUpperCase() + driver.slice(1));

    const unavailableReason = (quote: RateQuote) =>
        label_drivers.includes(quote.provider)
            ? t('admin.orders.ship.carrier_not_enabled')
            : t('admin.orders.ship.no_label_driver', { provider: driverName(quote.provider) });

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.orders.title'), href: '/admin/orders' },
                { title: order.order_number, href: `/admin/orders/${order.id}` },
                { title: t('admin.orders.ship.button'), href: '#' },
            ]}
        >
            <Head title={t('admin.orders.ship.title', { number: order.order_number })} />

            <div className="mx-auto max-w-3xl space-y-6 p-4 md:p-6">
                <div className="space-y-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={`/admin/orders/${order.id}`}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            {t('admin.orders.ship.back')}
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-bold">{t('admin.orders.ship.title', { number: order.order_number })}</h1>
                </div>

                <div className="grid gap-4 md:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <MapPin className="h-4 w-4" />
                                {t('admin.orders.ship.address')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-0.5 text-sm">
                            {address ? (
                                <>
                                    <p className="font-medium">
                                        {address.first_name} {address.last_name}
                                    </p>
                                    {address.company && <p>{address.company}</p>}
                                    <p>{address.address_line_1}</p>
                                    {address.address_line_2 && <p>{address.address_line_2}</p>}
                                    <p>
                                        {address.postal_code} {address.city} {address.state}
                                    </p>
                                    <p>{address.country}</p>
                                    {address.phone && <p className="text-muted-foreground">{address.phone}</p>}
                                    {addressWarnings.length > 0 && (
                                        <div className="mt-2 flex items-start gap-2 rounded-md border border-amber-500/30 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-400">
                                            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <div className="space-y-0.5">
                                                {addressWarnings.map((warning) => (
                                                    <p key={String(warning)}>{warning}</p>
                                                ))}
                                                <Link href={`/admin/orders/${order.id}/edit`} className="font-medium underline">
                                                    {t('admin.orders.ship.edit_address')}
                                                </Link>
                                            </div>
                                        </div>
                                    )}
                                </>
                            ) : (
                                <p className="text-muted-foreground">—</p>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Package className="h-4 w-4" />
                                {t('admin.orders.ship.items')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {(order.orderItems ?? []).map((item) => (
                                    <li key={item.id}>
                                        {item.quantity} × {item.product.name}
                                        {item.variant?.name ? <span className="text-muted-foreground"> ({item.variant.name})</span> : null}
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader className="space-y-3">
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Truck className="h-4 w-4" />
                                {t('admin.orders.ship.shipment')}
                            </CardTitle>
                            {labels_enabled && (
                                <div role="tablist" className="grid w-full grid-cols-2 gap-1 rounded-lg bg-muted p-1">
                                    {(['label', 'manual'] as const).map((value) => (
                                        <button
                                            key={value}
                                            type="button"
                                            role="tab"
                                            aria-selected={tab === value}
                                            onClick={() => switchTab(value)}
                                            className={cn(
                                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                                tab === value ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
                                            )}
                                        >
                                            {value === 'label' ? t('admin.orders.ship.tab_buy') : t('admin.orders.ship.tab_have')}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-5">
                            {tab === 'label' ? (
                                <>
                                    {!sender_configured && (
                                        <div className="flex flex-col gap-3 rounded-lg border border-amber-500/40 bg-amber-500/10 p-4 text-amber-800 sm:flex-row sm:items-center sm:justify-between dark:text-amber-300">
                                            <div className="flex items-start gap-3">
                                                <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />
                                                <div className="space-y-0.5 text-sm">
                                                    <p className="font-semibold">{t('admin.orders.ship.sender_not_configured_title')}</p>
                                                    <p className="text-xs text-amber-700/90 dark:text-amber-300/90">
                                                        {t('admin.orders.ship.sender_not_configured_warning')}
                                                    </p>
                                                </div>
                                            </div>
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="shrink-0 border-amber-500/40 bg-background text-xs"
                                                asChild
                                            >
                                                <Link href="/admin/settings/#shipping">{t('admin.orders.ship.configure_sender_settings')}</Link>
                                            </Button>
                                        </div>
                                    )}

                                    {/* Parcels: one summary line, the grid on demand */}
                                    <div className="space-y-3 border-b pb-4">
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <div className="min-w-0">
                                                <span className="font-medium">{t('admin.orders.ship.parcels')}</span>
                                                <span className="ml-2 text-muted-foreground">
                                                    {t('admin.orders.ship.parcels_total', {
                                                        count: data.parcels.length,
                                                        weight: totalWeight.toFixed(2),
                                                    })}
                                                    {parcelDims && ` · ${parcelDims}`}
                                                </span>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                aria-expanded={parcelsOpen}
                                                onClick={() => setParcelsOpen(!parcelsOpen)}
                                            >
                                                {parcelsOpen ? t('admin.orders.ship.done') : t('admin.orders.ship.edit')}
                                            </Button>
                                        </div>
                                        {parcelsOpen && (
                                            <div className="space-y-3">
                                                {data.parcels.map((parcel, index) => (
                                                    <div key={index} className="grid grid-cols-2 gap-2 rounded-md border p-3 md:grid-cols-5">
                                                        {parcel.label && <p className="col-span-full text-xs font-medium">{parcel.label}</p>}
                                                        <div className="space-y-1">
                                                            <Label htmlFor={`weight-${index}`} className="text-xs">
                                                                {t('admin.orders.ship.weight')}
                                                            </Label>
                                                            <Input
                                                                id={`weight-${index}`}
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                value={parcel.weight ?? ''}
                                                                onChange={(event) => setParcel(index, 'weight', event.target.value)}
                                                            />
                                                        </div>
                                                        {(['length', 'width', 'height'] as const).map((dim) => (
                                                            <div key={dim} className="space-y-1">
                                                                <Label htmlFor={`${dim}-${index}`} className="text-xs">
                                                                    {t(`admin.orders.ship.${dim}`)}
                                                                </Label>
                                                                <Input
                                                                    id={`${dim}-${index}`}
                                                                    type="number"
                                                                    step="0.1"
                                                                    min="0"
                                                                    value={parcel[dim] ?? ''}
                                                                    onChange={(event) => setParcel(index, dim, event.target.value)}
                                                                />
                                                            </div>
                                                        ))}
                                                        <div className="flex items-end">
                                                            {data.parcels.length > 1 && (
                                                                <Button type="button" variant="ghost" size="sm" onClick={() => removeParcel(index)}>
                                                                    {t('admin.orders.ship.remove_parcel')}
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </div>
                                                ))}
                                                <Button type="button" variant="outline" size="sm" onClick={addParcel}>
                                                    {t('admin.orders.ship.add_parcel')}
                                                </Button>
                                            </div>
                                        )}
                                    </div>

                                    {/* Options: pickup date, insurance (COD never applies: payments go through Stripe) */}
                                    <div className="space-y-3 border-b pb-4">
                                        <div className="space-y-1.5">
                                            <Label htmlFor="pickup_date" className="text-sm font-medium">
                                                {t('admin.orders.ship.pickup_date')}
                                            </Label>
                                            <Input
                                                id="pickup_date"
                                                type="date"
                                                className="max-w-48"
                                                min={minPickupDate}
                                                value={data.pickup_date}
                                                onChange={(event) => setData('pickup_date', event.target.value)}
                                                aria-invalid={!!errors.pickup_date}
                                            />
                                            <p className="text-xs text-muted-foreground">{t('admin.orders.ship.pickup_date_help')}</p>
                                            {errors.pickup_date && <p className="text-sm text-destructive">{errors.pickup_date}</p>}
                                        </div>
                                        <div className="flex items-center justify-between gap-3 text-sm">
                                            <div>
                                                <span className="font-medium">{t('admin.orders.ship.options')}</span>
                                                <span className="ml-2 text-muted-foreground">
                                                    {data.insurance_amount
                                                        ? t('admin.orders.ship.insured_for', { amount: Number(data.insurance_amount).toFixed(2) })
                                                        : t('admin.orders.ship.insurance_none')}
                                                </span>
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                aria-expanded={optionsOpen}
                                                onClick={() => setOptionsOpen(!optionsOpen)}
                                            >
                                                {optionsOpen ? t('admin.orders.ship.done') : t('admin.orders.ship.edit')}
                                            </Button>
                                        </div>
                                        {optionsOpen && (
                                            <div className="space-y-2">
                                                <div className="flex items-center gap-2">
                                                    <Checkbox
                                                        id="insure"
                                                        checked={data.insurance_amount !== ''}
                                                        onCheckedChange={(checked) =>
                                                            setData('insurance_amount', checked === true ? orderValue.toFixed(2) : '')
                                                        }
                                                    />
                                                    <Label htmlFor="insure">
                                                        {t('admin.orders.ship.insure_order_value', { amount: orderValue.toFixed(2) })}
                                                    </Label>
                                                </div>
                                                {data.insurance_amount !== '' && (
                                                    <div className="max-w-48 space-y-1 pl-6">
                                                        <Label htmlFor="insurance_amount" className="text-xs">
                                                            {t('admin.orders.ship.insurance_amount')}
                                                        </Label>
                                                        <Input
                                                            id="insurance_amount"
                                                            type="number"
                                                            step="0.01"
                                                            min="0"
                                                            value={data.insurance_amount}
                                                            onChange={(event) => setData('insurance_amount', event.target.value)}
                                                        />
                                                    </div>
                                                )}
                                                {errors.insurance_amount && <p className="text-sm text-destructive">{errors.insurance_amount}</p>}
                                            </div>
                                        )}
                                    </div>

                                    {/* Rates: fetched automatically, best one preselected */}
                                    {sender_configured && (
                                        <div className="space-y-3">
                                            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                                <h4 className="text-sm font-medium">{t('admin.orders.ship.rates')}</h4>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        className="h-7 gap-1 px-2 text-xs"
                                                        onClick={() => setSortBy(sortBy === 'price' ? 'provider' : 'price')}
                                                    >
                                                        <ArrowUpDown className="h-3.5 w-3.5" />
                                                        {sortBy === 'price'
                                                            ? t('admin.orders.ship.sort_price')
                                                            : t('admin.orders.ship.sort_provider')}
                                                    </Button>
                                                    <span>{t('admin.orders.ship.customer_paid', { amount: customerPaid.toFixed(2) })}</span>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-7 w-7"
                                                        aria-label={t('admin.orders.ship.refresh')}
                                                        disabled={rates.status === 'loading'}
                                                        onClick={() => setRefreshCount((n) => n + 1)}
                                                    >
                                                        <RefreshCw className={cn('h-3.5 w-3.5', rates.status === 'loading' && 'animate-spin')} />
                                                    </Button>
                                                </div>
                                            </div>

                                            {rates.status === 'error' && (
                                                <div className="flex items-start gap-2 rounded-md border border-destructive/30 bg-destructive/10 p-2.5 text-xs text-destructive">
                                                    <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
                                                    <span>{rates.message}</span>
                                                </div>
                                            )}

                                            {rates.status === 'loading' && rates.quotes.length === 0 && (
                                                <div className="space-y-2" aria-busy="true">
                                                    {[0, 1, 2].map((i) => (
                                                        <div key={i} className="h-14 animate-pulse rounded-md border bg-muted/40" />
                                                    ))}
                                                </div>
                                            )}

                                            {rates.quotes.length > 0 && (
                                                <div
                                                    role="radiogroup"
                                                    aria-label={t('admin.orders.ship.rates')}
                                                    aria-busy={rates.status === 'loading'}
                                                    className={cn(
                                                        'space-y-2 transition-opacity',
                                                        rates.status === 'loading' && 'pointer-events-none opacity-50',
                                                    )}
                                                >
                                                    {visibleQuotes.map((quote) => {
                                                        const key = quoteKey(quote);
                                                        const isSelected = selectedKey === key;
                                                        const canBuy = Boolean(quote.store_carrier_code);
                                                        const margin = quote.margin_delta ?? customerPaid - quote.total_price;

                                                        return (
                                                            <div
                                                                key={key}
                                                                className={cn(
                                                                    'rounded-md border bg-card transition-colors',
                                                                    isSelected
                                                                        ? 'border-primary ring-2 ring-primary/20'
                                                                        : 'hover:border-muted-foreground/30',
                                                                    !canBuy && 'opacity-60 hover:border-border',
                                                                )}
                                                            >
                                                                <div className="flex items-start">
                                                                    <button
                                                                        type="button"
                                                                        role="radio"
                                                                        aria-checked={isSelected}
                                                                        disabled={!canBuy}
                                                                        onClick={() => selectQuote(quote)}
                                                                        className="flex min-w-0 flex-1 items-center gap-3 p-3 text-left disabled:cursor-not-allowed"
                                                                    >
                                                                        <span
                                                                            className={cn(
                                                                                'flex h-4 w-4 shrink-0 items-center justify-center rounded-full border',
                                                                                isSelected && 'border-primary',
                                                                            )}
                                                                        >
                                                                            {isSelected && <span className="h-2 w-2 rounded-full bg-primary" />}
                                                                        </span>
                                                                        <span className="min-w-0 flex-1 space-y-0.5">
                                                                            <span className="flex flex-wrap items-center gap-1.5">
                                                                                <span className="text-sm font-semibold break-words">
                                                                                    {quote.carrier_name}
                                                                                </span>
                                                                                <span className="text-xs text-muted-foreground">
                                                                                    {quote.service_name}
                                                                                </span>
                                                                                {recommended && key === quoteKey(recommended) && (
                                                                                    <Badge className="px-1.5 py-0 text-[10px]">
                                                                                        {t('admin.orders.ship.recommended')}
                                                                                    </Badge>
                                                                                )}
                                                                                {isLastUsed(quote) && (
                                                                                    <Badge variant="outline" className="px-1.5 py-0 text-[10px]">
                                                                                        {t('admin.orders.ship.last_used')}
                                                                                    </Badge>
                                                                                )}
                                                                            </span>
                                                                            <span className="flex flex-wrap items-center gap-x-2 gap-y-0.5 text-xs text-muted-foreground">
                                                                                <span>{driverName(quote.provider)}</span>
                                                                                {quote.estimated_delivery && (
                                                                                    <span className="inline-flex items-center gap-1">
                                                                                        <Clock className="h-3 w-3" />
                                                                                        {quote.estimated_delivery}
                                                                                    </span>
                                                                                )}
                                                                                {quote.dimensional_weight_applied && (
                                                                                    <span className="text-amber-700 dark:text-amber-400">
                                                                                        {t('admin.orders.ship.volumetric')}
                                                                                        {quote.taxable_weight_kg != null &&
                                                                                            ` ${quote.taxable_weight_kg} kg`}
                                                                                    </span>
                                                                                )}
                                                                                {!canBuy && (
                                                                                    <span className="text-amber-700 dark:text-amber-400">
                                                                                        {unavailableReason(quote)}
                                                                                    </span>
                                                                                )}
                                                                            </span>
                                                                        </span>
                                                                        <span className="shrink-0 text-right">
                                                                            <span className="block text-base font-bold">
                                                                                {euro(quote.total_price)}
                                                                            </span>
                                                                            <span
                                                                                className={cn(
                                                                                    'block text-xs font-medium',
                                                                                    margin >= 0
                                                                                        ? 'text-emerald-700 dark:text-emerald-400'
                                                                                        : 'text-red-700 dark:text-red-400',
                                                                                )}
                                                                            >
                                                                                {margin >= 0 ? `+${euro(margin)}` : `−${euro(Math.abs(margin))}`}
                                                                            </span>
                                                                        </span>
                                                                    </button>
                                                                    {canBuy && (
                                                                        <Button
                                                                            type="button"
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="mt-2.5 mr-1.5 h-8 w-8 shrink-0"
                                                                            aria-pressed={isFavourite(quote)}
                                                                            aria-label={t(
                                                                                isFavourite(quote)
                                                                                    ? 'admin.orders.ship.favourite_remove'
                                                                                    : 'admin.orders.ship.favourite_add',
                                                                                {
                                                                                    carrier: quote.carrier_name,
                                                                                },
                                                                            )}
                                                                            onClick={() => toggleFavourite(quote)}
                                                                        >
                                                                            <Star
                                                                                className={cn(
                                                                                    'h-4 w-4',
                                                                                    isFavourite(quote)
                                                                                        ? 'fill-amber-400 text-amber-500'
                                                                                        : 'text-muted-foreground',
                                                                                )}
                                                                            />
                                                                        </Button>
                                                                    )}
                                                                </div>
                                                                {/* carrier notes (limits, terms) can run long: collapsed by default */}
                                                                {!!quote.warnings?.length && (
                                                                    <details className="group border-t px-3 py-2 text-xs">
                                                                        <summary className="flex cursor-pointer list-none items-center gap-1 text-amber-700 dark:text-amber-400 [&::-webkit-details-marker]:hidden">
                                                                            <AlertTriangle className="h-3 w-3 shrink-0" />
                                                                            {t('admin.orders.ship.notes', { count: quote.warnings.length })}
                                                                            <ChevronDown className="h-3 w-3 shrink-0 transition-transform group-open:rotate-180" />
                                                                        </summary>
                                                                        <div className="mt-1.5 space-y-1 break-words text-muted-foreground">
                                                                            {quote.warnings.map((warning) => (
                                                                                <p key={warning}>{warning}</p>
                                                                            ))}
                                                                        </div>
                                                                    </details>
                                                                )}
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                            )}

                                            {rates.quotes.length > VISIBLE_QUOTES &&
                                                (showAllQuotes || visibleQuotes.length < rates.quotes.length) && (
                                                    <Button
                                                        type="button"
                                                        variant="link"
                                                        size="sm"
                                                        className="h-auto p-0"
                                                        onClick={() => setShowAllQuotes(!showAllQuotes)}
                                                    >
                                                        {showAllQuotes
                                                            ? t('admin.orders.ship.show_less')
                                                            : t('admin.orders.ship.show_more', { count: rates.quotes.length - visibleQuotes.length })}
                                                    </Button>
                                                )}

                                            {rates.status === 'ready' && rates.quotes.length === 0 && (
                                                <p className="py-2 text-center text-xs text-muted-foreground">
                                                    {t('admin.orders.ship.no_quotes_returned')}
                                                </p>
                                            )}

                                            {rates.status === 'ready' && buyable.length > 0 && !recommended && (
                                                <p className="text-xs text-amber-700 dark:text-amber-400">
                                                    {t('admin.orders.ship.no_positive_margin')}
                                                </p>
                                            )}

                                            {rates.status === 'ready' && rates.quotes.length > 0 && buyable.length === 0 && (
                                                <p className="text-xs text-amber-700 dark:text-amber-400">{t('admin.orders.ship.none_buyable')}</p>
                                            )}

                                            <ShippingIssues
                                                orderId={order.id}
                                                address={order.shippingAddress ?? null}
                                                issues={rates.issues}
                                                providerName={driverName}
                                                onEditParcels={() => setParcelsOpen(true)}
                                                onFixed={() => setRefreshCount((n) => n + 1)}
                                            />

                                            {selected && balanceState && (
                                                <div
                                                    className={cn(
                                                        'flex items-center gap-2 rounded-md border p-2 text-xs',
                                                        balanceState === 'insufficient'
                                                            ? 'border-destructive/30 bg-destructive/10 text-destructive'
                                                            : 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-400',
                                                    )}
                                                >
                                                    <Wallet className="h-3.5 w-3.5 shrink-0" />
                                                    {balanceState === 'insufficient'
                                                        ? t('admin.orders.ship.balance_insufficient', {
                                                              provider: driverName(selected.provider),
                                                              amount: Number(selectedBalance).toFixed(2),
                                                          })
                                                        : t('admin.orders.ship.balance_low', {
                                                              provider: driverName(selected.provider),
                                                              amount: Number(selectedBalance).toFixed(2),
                                                          })}
                                                </div>
                                            )}

                                            {(errors as Record<string, string>).label && (
                                                <p className="text-sm text-destructive">{(errors as Record<string, string>).label}</p>
                                            )}
                                            {errors.tracking_carrier && <p className="text-sm text-destructive">{errors.tracking_carrier}</p>}
                                        </div>
                                    )}
                                </>
                            ) : (
                                <>
                                    {/* A shipment bought elsewhere: where it was bought decides how it is tracked */}
                                    {tracking_drivers.length > 1 && (
                                        <div className="space-y-2">
                                            <Label id="bought-on">{t('admin.orders.ship.bought_on')}</Label>
                                            <div role="radiogroup" aria-labelledby="bought-on" className="flex flex-wrap gap-2">
                                                {tracking_drivers.map((driver) => (
                                                    <button
                                                        key={driver}
                                                        type="button"
                                                        role="radio"
                                                        aria-checked={data.tracking_driver === driver}
                                                        onClick={() => setData('tracking_driver', driver)}
                                                        className={cn(
                                                            'rounded-md border px-3 py-1.5 text-sm transition-colors',
                                                            data.tracking_driver === driver
                                                                ? 'border-primary bg-primary/10 font-medium'
                                                                : 'hover:border-muted-foreground/30',
                                                        )}
                                                    >
                                                        {driverName(driver)}
                                                    </button>
                                                ))}
                                            </div>
                                            {data.tracking_driver && (
                                                <p className="text-xs text-muted-foreground">
                                                    {data.tracking_driver === 'manual'
                                                        ? t('admin.orders.ship.bought_on_help_manual')
                                                        : t('admin.orders.ship.bought_on_help_api', { provider: driverName(data.tracking_driver) })}
                                                </p>
                                            )}
                                            {errors.tracking_driver && <p className="text-sm text-destructive">{errors.tracking_driver}</p>}
                                        </div>
                                    )}

                                    <div className="space-y-2">
                                        <Label htmlFor="tracking_carrier">{t('tracking_admin.carrier')}</Label>
                                        {carriers.length === 0 ? (
                                            <p className="text-sm text-destructive">{t('admin.orders.ship.no_carriers')}</p>
                                        ) : (
                                            <Select value={data.tracking_carrier} onValueChange={(value) => setData('tracking_carrier', value)}>
                                                <SelectTrigger id="tracking_carrier" aria-invalid={!!errors.tracking_carrier}>
                                                    <SelectValue placeholder={t('tracking_admin.carrier_select')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {carriers.map((carrier) => (
                                                        <SelectItem key={carrier.code} value={carrier.code}>
                                                            {carrier.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        )}
                                        {errors.tracking_carrier && <p className="text-sm text-destructive">{errors.tracking_carrier}</p>}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="tracking_number">{t('admin.orders.ship.tracking_number')}</Label>
                                        <Input
                                            id="tracking_number"
                                            value={data.tracking_number}
                                            onChange={(event) => setData('tracking_number', event.target.value)}
                                            placeholder={t('admin.orders.ship.tracking_placeholder')}
                                            maxLength={100}
                                            autoComplete="off"
                                            aria-invalid={!!errors.tracking_number}
                                        />
                                        {errors.tracking_number && <p className="text-sm text-destructive">{errors.tracking_number}</p>}
                                    </div>

                                    {withoutNumber && (
                                        <div className="space-y-3 rounded-md border p-3">
                                            <div className="flex items-start gap-2">
                                                <Checkbox
                                                    id="ship_without_tracking"
                                                    checked={data.ship_without_tracking}
                                                    onCheckedChange={(checked) => setData('ship_without_tracking', checked === true)}
                                                />
                                                <div className="space-y-1">
                                                    <Label htmlFor="ship_without_tracking">{t('admin.orders.ship.without_tracking')}</Label>
                                                    <p className="text-xs text-muted-foreground">{t('admin.orders.ship.without_tracking_help')}</p>
                                                </div>
                                            </div>
                                            {data.ship_without_tracking && shipped_email_enabled && (
                                                <div className="flex items-center gap-2 pl-6">
                                                    <Checkbox
                                                        id="notify_customer"
                                                        checked={data.notify_customer}
                                                        onCheckedChange={(checked) => setData('notify_customer', checked === true)}
                                                    />
                                                    <Label htmlFor="notify_customer">{t('admin.orders.ship.notify_now')}</Label>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                </>
                            )}
                        </CardContent>

                        {/* Sticky footer: the button names what is about to happen */}
                        <div className="sticky bottom-0 flex flex-col gap-2 rounded-b-xl border-t bg-background/95 px-6 py-3 backdrop-blur sm:flex-row sm:items-center sm:justify-between">
                            <p className="text-xs text-muted-foreground">{emailHint}</p>
                            <Button type="submit" disabled={!canSubmit} className="shrink-0">
                                {processing || (tab === 'label' && rates.status === 'loading') ? (
                                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                ) : (
                                    <Truck className="mr-2 h-4 w-4" />
                                )}
                                {tab === 'manual'
                                    ? t('admin.orders.ship.submit')
                                    : selected
                                      ? t('admin.orders.ship.tab_buy')
                                      : t('admin.orders.ship.select_rate')}
                            </Button>
                        </div>
                    </Card>
                </form>
            </div>

            {selected && data.pickup_date !== '' && (
                <BuyLabelDialog
                    open={buying}
                    onOpenChange={setBuying}
                    onConfirm={send}
                    processing={processing}
                    quote={selected}
                    providerName={driverName(selected.provider)}
                    pickupDate={data.pickup_date}
                    margin={selected.margin_delta ?? customerPaid - selected.total_price}
                    address={order.shippingAddress ?? null}
                    balance={selectedBalance}
                />
            )}

            <AlertDialog open={confirming} onOpenChange={(open) => !open && !processing && setConfirming(false)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('admin.orders.ship.confirm_title')}</AlertDialogTitle>
                        <AlertDialogDescription>{emailHint}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={processing}>{t('admin.orders.ship.cancel')}</AlertDialogCancel>
                        <AlertDialogAction onClick={send} disabled={processing}>
                            {t('admin.orders.ship.confirm')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </AppLayout>
    );
}

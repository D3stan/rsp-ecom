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
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, MapPin, Package, Truck } from 'lucide-react';
import { FormEvent, useState } from 'react';

interface Order {
    id: number;
    order_number: string;
    tracking_number?: string | null;
    tracking_carrier?: string | null;
    tracking_driver?: string | null;
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
    label_driver_carriers?: Record<string, string[]>;
    default_label_driver?: string;
    tracking_drivers?: string[];
    default_tracking_driver?: string;
}

const DEFAULT_DRIVER = '__default__';

type ShipForm = {
    tracking_carrier: string;
    tracking_number: string;
    tracking_driver: string;
    label_driver: string;
    ship_without_tracking: boolean;
    notify_customer: boolean;
    create_label: boolean;
    parcels: Parcel[];
};

/**
 * Ship form: carrier, provider, and tracking number or label parcels. The server (ShippingService) records the shipment,
 * moves the order to "shipped" and sends one shipped email, with the tracking. Shipping without a number is possible
 * after an explicit confirmation; the customer is then emailed now only when "notify now" is ticked.
 */
export default function OrderShip({
    order,
    carriers = [],
    shipped_email_enabled = true,
    labels_enabled = false,
    parcels = [],
    label_drivers = [],
    label_driver_carriers = {},
    default_label_driver = '',
    tracking_drivers = [],
    default_tracking_driver = '',
}: Props) {
    const { t } = useTranslation();
    const [confirming, setConfirming] = useState(false);
    // with labels on the default flow is "create label"; the admin can switch to typing a tracking number
    const [labelMode, setLabelMode] = useState(labels_enabled);
    const initialTrackingDriver =
        order.tracking_driver && tracking_drivers.includes(order.tracking_driver)
            ? order.tracking_driver
            : tracking_drivers.includes(default_tracking_driver)
              ? default_tracking_driver
              : '';
    const initialLabelDriver = label_drivers.includes(default_label_driver) ? default_label_driver : (label_drivers[0] ?? '');
    const initialSupportedCarriers =
        labels_enabled && label_driver_carriers[initialLabelDriver] !== undefined
            ? label_driver_carriers[initialLabelDriver]
            : null;
    const initialCarrierValid =
        carriers.some((carrier) => carrier.code === order.tracking_carrier) &&
        (initialSupportedCarriers === null || initialSupportedCarriers.includes(order.tracking_carrier as string));

    const { data, setData, post, processing, errors } = useForm<ShipForm>({
        // a carrier/number saved earlier on the order is prefilled
        tracking_carrier: initialCarrierValid ? (order.tracking_carrier as string) : '',
        tracking_number: order.tracking_number ?? '',
        tracking_driver: initialTrackingDriver,
        label_driver: initialLabelDriver,
        ship_without_tracking: false,
        notify_customer: false,
        create_label: labels_enabled,
        parcels: parcels.length > 0 ? parcels : [{ weight: '', length: '', width: '', height: '' }],
    });

    const displayedCarriers =
        labelMode && label_driver_carriers[data.label_driver] !== undefined
            ? carriers.filter((carrier) => label_driver_carriers[data.label_driver].includes(carrier.code))
            : carriers;

    const withoutNumber = data.tracking_number.trim() === '';
    const canSubmit = displayedCarriers.some((carrier) => carrier.code === data.tracking_carrier)
        && (labelMode || !withoutNumber || data.ship_without_tracking)
        && !processing;

    const send = () => post(`/admin/orders/${order.id}/ship`, { preserveScroll: true, onFinish: () => setConfirming(false) });

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

    const handleLabelDriverChange = (driver: string) => {
        const supported = label_driver_carriers[driver];
        if (supported !== undefined && data.tracking_carrier !== '' && !supported.includes(data.tracking_carrier)) {
            setData((prev) => ({
                ...prev,
                label_driver: driver,
                tracking_carrier: '',
            }));
        } else {
            setData('label_driver', driver);
        }
    };

    const switchLabelMode = (on: boolean) => {
        setLabelMode(on);
        if (on) {
            const supported = label_driver_carriers[data.label_driver];
            if (supported !== undefined && data.tracking_carrier !== '' && !supported.includes(data.tracking_carrier)) {
                setData((prev) => ({
                    ...prev,
                    create_label: true,
                    tracking_carrier: '',
                }));
                return;
            }
        }
        setData('create_label', on);
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!canSubmit) return;

        // manual mode with no tracking number: ask first (the customer gets no tracking link now)
        if (!labelMode && withoutNumber) {
            setConfirming(true);
            return;
        }

        send();
    };

    const emailHint = !shipped_email_enabled
        ? t('admin.orders.ship.email_disabled')
        : !withoutNumber
          ? t('admin.orders.ship.email_with_tracking')
          : data.notify_customer
            ? t('admin.orders.ship.email_now')
            : t('admin.orders.ship.email_later');

    const address = order.shippingAddress;

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
                                </>
                            ) : (
                                <p className="text-muted-foreground">—</p>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <form onSubmit={submit}>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <Truck className="h-4 w-4" />
                                {t('admin.orders.ship.shipment')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-5">
                            <div className="space-y-2">
                                <Label htmlFor="tracking_carrier">{t('tracking_admin.carrier')}</Label>
                                {displayedCarriers.length === 0 ? (
                                    <p className="text-sm text-destructive">{t('admin.orders.ship.no_carriers')}</p>
                                ) : (
                                    <Select value={data.tracking_carrier} onValueChange={(value) => setData('tracking_carrier', value)}>
                                        <SelectTrigger id="tracking_carrier" aria-invalid={!!errors.tracking_carrier}>
                                            <SelectValue placeholder={t('tracking_admin.carrier_select')} />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {displayedCarriers.map((carrier) => (
                                                <SelectItem key={carrier.code} value={carrier.code}>
                                                    {carrier.name}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                )}
                                {errors.tracking_carrier && <p className="text-sm text-destructive">{errors.tracking_carrier}</p>}
                            </div>

                            {labelMode ? (
                                <div className="space-y-4">
                                    {label_drivers.length > 1 && (
                                        <div className="space-y-2">
                                            <Label htmlFor="label_driver">{t('admin.orders.ship.provider', { defaultValue: 'Label Provider' })}</Label>
                                            <Select value={data.label_driver} onValueChange={handleLabelDriverChange}>
                                                <SelectTrigger id="label_driver" aria-invalid={!!errors.label_driver}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {label_drivers.map((driver) => (
                                                        <SelectItem key={driver} value={driver}>
                                                            {driver}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.label_driver && <p className="text-sm text-destructive">{errors.label_driver}</p>}
                                        </div>
                                    )}
                                    {(errors as Record<string, string>).label && (
                                        <p className="text-sm text-destructive">{(errors as Record<string, string>).label}</p>
                                    )}
                                    <div className="space-y-3">
                                        <Label>{t('admin.orders.ship.parcels')}</Label>
                                        {data.parcels.map((parcel, index) => (
                                            <div key={index} className="grid grid-cols-2 gap-2 rounded-md border p-3 md:grid-cols-5">
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
                                </div>
                            ) : (
                                <>
                                    {tracking_drivers.length > 1 && (
                                        <div className="space-y-2">
                                            <Label htmlFor="tracking_driver">
                                                {t('tracking_admin.provider', { defaultValue: 'Tracking Provider' })}
                                            </Label>
                                            <Select
                                                value={data.tracking_driver || DEFAULT_DRIVER}
                                                onValueChange={(value) => setData('tracking_driver', value === DEFAULT_DRIVER ? '' : value)}
                                            >
                                                <SelectTrigger id="tracking_driver" aria-invalid={!!errors.tracking_driver}>
                                                    <SelectValue placeholder={t('tracking_admin.provider_default', { defaultValue: 'Default' })} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value={DEFAULT_DRIVER}>
                                                        {t('tracking_admin.provider_default', { defaultValue: 'Default' })}
                                                    </SelectItem>
                                                    {tracking_drivers.map((driver) => (
                                                        <SelectItem key={driver} value={driver}>
                                                            {driver}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.tracking_driver && <p className="text-sm text-destructive">{errors.tracking_driver}</p>}
                                        </div>
                                    )}
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

                            {!labelMode && <p className="text-sm text-muted-foreground">{emailHint}</p>}

                            {labels_enabled && (
                                <button type="button" className="text-sm text-muted-foreground underline" onClick={() => switchLabelMode(!labelMode)}>
                                    {labelMode ? t('admin.orders.ship.enter_tracking_instead') : t('admin.orders.ship.create_label_instead')}
                                </button>
                            )}

                            <div className="flex justify-end">
                                <Button type="submit" disabled={!canSubmit}>
                                    <Truck className="mr-2 h-4 w-4" />
                                    {labelMode ? t('admin.orders.ship.create_label') : t('admin.orders.ship.submit')}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </form>
            </div>

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

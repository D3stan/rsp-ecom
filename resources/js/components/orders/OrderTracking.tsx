import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import useTranslation from '@/hooks/useTranslation';
import { type TrackingEvent } from '@/types';
import { ExternalLink, MapPin, Truck, XCircle } from 'lucide-react';

interface TrackedOrder {
    status: string;
    payment_status?: string;
    tracking_number?: string | null;
    tracking_status?: string | null;
    tracking_last_synced_at?: string | null;
    carrier_name?: string | null;
    tracking_url?: string | null;
    tracking_events?: TrackingEvent[];
}

/**
 * Customer-facing shipment tracking for an order: a "Track with {carrier}" button, the carrier status, the last
 * update time and a compact event list. Cancelled orders show a dedicated state (and "Refunded" when the payment was
 * refunded). Shared by the account orders page and the signed guest order page. No raw carrier codes are shown.
 */
export default function OrderTracking({ order }: { order: TrackedOrder }) {
    const { t } = useTranslation();
    const status = order.status.toLowerCase();
    const payment = (order.payment_status ?? '').toLowerCase();

    const statusLabel = (value?: string | null) => (value ? t(`tracking_admin.status.${value}`) : null);
    const formatTime = (value?: string | null) => (value ? new Date(value).toLocaleString() : '');

    if (status === 'cancelled') {
        const refunded = payment === 'refunded' || payment === 'partially_refunded';
        return (
            <div className="flex items-center gap-2 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-800">
                <XCircle className="h-4 w-4 shrink-0" />
                <span>{refunded ? t('orders.refunded_state') : t('orders.cancelled_state')}</span>
            </div>
        );
    }

    if (!order.tracking_number && (!order.tracking_events || order.tracking_events.length === 0)) {
        return null;
    }

    const events = order.tracking_events ?? [];

    return (
        <div className="space-y-3 rounded-lg border p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2 text-sm">
                    <Truck className="h-4 w-4 text-muted-foreground" />
                    {order.tracking_status ? (
                        <Badge variant="secondary">{statusLabel(order.tracking_status)}</Badge>
                    ) : (
                        order.tracking_number && <span className="font-mono text-xs">{order.tracking_number}</span>
                    )}
                </div>
                {order.tracking_url && (
                    <Button variant="outline" size="sm" asChild>
                        <a href={order.tracking_url} target="_blank" rel="noopener noreferrer">
                            <ExternalLink className="mr-1 h-3.5 w-3.5" />
                            {order.carrier_name ? t('orders.track_with', { carrier: order.carrier_name }) : t('orders.track_package')}
                        </a>
                    </Button>
                )}
            </div>

            {order.tracking_last_synced_at && (
                <p className="text-xs text-muted-foreground">{t('orders.last_update', { time: formatTime(order.tracking_last_synced_at) })}</p>
            )}

            {events.length > 0 ? (
                <ol className="space-y-2">
                    {events.map((event, index) => (
                        <li key={index} className="flex gap-2 text-sm">
                            <MapPin className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                            <div>
                                <p>
                                    <span className="font-medium">{statusLabel(event.status)}</span>
                                    {event.description ? ` — ${event.description}` : ''}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {[event.location, formatTime(event.occurred_at)].filter(Boolean).join(' · ')}
                                </p>
                            </div>
                        </li>
                    ))}
                </ol>
            ) : (
                order.tracking_number && <p className="text-xs text-muted-foreground">{t('orders.no_updates_yet')}</p>
            )}
        </div>
    );
}

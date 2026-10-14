import useTranslation from '@/hooks/useTranslation';
import { CheckCircle, CreditCard, Package, StickyNote, Truck, XCircle } from 'lucide-react';
import { type ReactNode } from 'react';

/** One order_events row as Admin\OrdersController::show sends it (OrderEvent::toTimelineArray). */
export interface OrderTimelineEvent {
    id: number;
    type: 'status_changed' | 'refund' | 'refund_failed' | 'note' | string;
    from_status: string | null;
    to_status: string | null;
    reason: string | null;
    source: string | null;
    payload: Record<string, unknown> | null;
    actor: { id: number; name: string } | null;
    created_at: string;
}

interface Props {
    createdAt: string;
    status: string;
    events: OrderTimelineEvent[];
    formatDate: (date: string) => string;
}

function Step({ icon, tone, title, children }: { icon: ReactNode; tone: string; title: string; children?: ReactNode }) {
    return (
        <div className="flex items-start space-x-3">
            <div className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${tone}`}>{icon}</div>
            <div className="min-w-0">
                <p className="font-medium">{title}</p>
                {children}
            </div>
        </div>
    );
}

function iconFor(event: OrderTimelineEvent): [ReactNode, string] {
    if (event.type === 'refund' || event.type === 'refund_failed') {
        return [<CreditCard className="h-4 w-4 text-amber-600" />, 'bg-amber-100'];
    }
    if (event.type === 'note') {
        return [<StickyNote className="h-4 w-4 text-gray-600" />, 'bg-gray-100'];
    }

    switch (event.to_status) {
        case 'delivered':
            return [<CheckCircle className="h-4 w-4 text-green-600" />, 'bg-green-100'];
        case 'shipped':
            return [<Truck className="h-4 w-4 text-purple-600" />, 'bg-purple-100'];
        case 'cancelled':
            return [<XCircle className="h-4 w-4 text-red-600" />, 'bg-red-100'];
        default:
            return [<Package className="h-4 w-4 text-blue-600" />, 'bg-blue-100'];
    }
}

/**
 * The order's real history: "placed" at created_at, then every recorded event with its own time, who made it (or the
 * system and its source) and the reason. Orders from before the audit trail only show "placed" and a hint.
 */
export default function OrderTimeline({ createdAt, status, events, formatDate }: Props) {
    const { t } = useTranslation();
    const statusLabel = (value: string | null) => (value ? t(`admin.orders.status.${value}`) : '');

    const title = (event: OrderTimelineEvent) => {
        switch (event.type) {
            case 'status_changed':
                return t('admin.orders.timeline.status_changed', { from: statusLabel(event.from_status), to: statusLabel(event.to_status) });
            case 'refund':
                return t('admin.orders.timeline.refund');
            case 'refund_failed':
                return t('admin.orders.timeline.refund_failed');
            default:
                return t('admin.orders.timeline.note');
        }
    };

    const who = (event: OrderTimelineEvent) =>
        event.actor
            ? event.actor.name
            : event.source
              ? t('admin.orders.timeline.by_system_source', { source: event.source })
              : t('admin.orders.timeline.by_system');

    return (
        <>
            <Step icon={<CheckCircle className="h-4 w-4 text-green-600" />} tone="bg-green-100" title={t('admin.orders.timeline.placed')}>
                <p className="text-sm text-muted-foreground">{formatDate(createdAt)}</p>
            </Step>

            {events.map((event) => {
                const [icon, tone] = iconFor(event);

                return (
                    <Step key={event.id} icon={icon} tone={tone} title={title(event)}>
                        <p className="text-sm text-muted-foreground">
                            {formatDate(event.created_at)} · {who(event)}
                        </p>
                        {event.reason && <p className="text-sm break-words text-muted-foreground italic">“{event.reason}”</p>}
                    </Step>
                );
            })}

            {events.length === 0 && status !== 'pending' && <p className="text-sm text-muted-foreground">{t('admin.orders.timeline.no_history')}</p>}
        </>
    );
}

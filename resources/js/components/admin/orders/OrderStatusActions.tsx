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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import useTranslation from '@/hooks/useTranslation';
import { forwardTransitions } from '@/lib/order-transitions';
import { router } from '@inertiajs/react';
import { useState } from 'react';

interface Props {
    orderId: number;
    status: string;
    allowedTransitions: string[];
    disabled?: boolean;
    /** Forward move chosen (the page keeps its own checks, e.g. shipping without a tracking number). */
    onAdvance: (status: string) => void;
    /** Money that can still be refunded through Stripe: when > 0, cancelling asks "refund now" or "refund later". */
    refundableCents?: number;
    currency?: string;
}

type Pending = 'cancel' | 'undo_ship' | null;

/**
 * Status buttons of the admin order page: only the moves the order lifecycle allows. Cancelling and undoing a shipment
 * ask for confirmation first; undoing a shipment needs a reason (kept with the status change).
 */
export default function OrderStatusActions({
    orderId,
    status,
    allowedTransitions,
    disabled = false,
    onAdvance,
    refundableCents = 0,
    currency = 'EUR',
}: Props) {
    const { t } = useTranslation();
    const [pending, setPending] = useState<Pending>(null);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    const canCancel = allowedTransitions.includes('cancelled');
    const canUndoShip = status === 'shipped' && allowedTransitions.includes('processing');
    const reasonMissing = pending === 'undo_ship' && reason.trim() === '';

    const open = (action: Pending) => {
        setReason('');
        setPending(action);
    };

    // A paid order: cancelling either refunds what is left right away, or leaves it "Refund due" for customer service
    const paid = refundableCents > 0;
    const refundable = new Intl.NumberFormat('it-IT', { style: 'currency', currency: currency || 'EUR' }).format(refundableCents / 100);

    const confirm = (refund: 'now' | 'later' | null = null) => {
        if (reasonMissing) return;

        const [url, data] =
            pending === 'cancel'
                ? [`/admin/orders/${orderId}/cancel`, { reason: reason.trim() || null, refund }]
                : [`/admin/orders/${orderId}/status`, { status: 'processing', reason: reason.trim() }];

        setBusy(true);
        router.patch(url, data, {
            preserveScroll: true,
            onSuccess: () => setPending(null),
            onFinish: () => setBusy(false),
        });
    };

    const forward = forwardTransitions(status, allowedTransitions);

    if (forward.length === 0 && !canCancel && !canUndoShip) {
        return <p className="text-sm text-muted-foreground">{t('admin.orders.status_final')}</p>;
    }

    return (
        <>
            <div className="flex flex-wrap gap-2">
                {forward.map((next) => (
                    <Button key={next} variant="outline" size="sm" onClick={() => onAdvance(next)} disabled={disabled || busy}>
                        {t('admin.orders.mark_as', { status: t(`admin.orders.status.${next}`) })}
                    </Button>
                ))}
                {canUndoShip && (
                    <Button variant="outline" size="sm" onClick={() => open('undo_ship')} disabled={disabled || busy}>
                        {t('admin.orders.undo_ship')}
                    </Button>
                )}
                {canCancel && (
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => open('cancel')}
                        disabled={disabled || busy}
                        className="text-red-600 hover:text-red-700"
                    >
                        {t('admin.orders.cancel_order')}
                    </Button>
                )}
            </div>

            <AlertDialog open={pending !== null} onOpenChange={(next) => !next && !busy && setPending(null)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            {pending === 'cancel' ? t('admin.orders.cancel_confirm_title') : t('admin.orders.undo_ship_confirm_title')}
                        </AlertDialogTitle>
                        <AlertDialogDescription>
                            {pending === 'cancel' ? t('admin.orders.cancel_confirm_body') : t('admin.orders.undo_ship_confirm_body')}
                        </AlertDialogDescription>
                        {pending === 'cancel' && paid && (
                            <p className="text-sm text-muted-foreground">{t('admin.orders.cancel_paid_hint', { amount: refundable })}</p>
                        )}
                    </AlertDialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="status-change-reason">
                            {pending === 'undo_ship' ? t('admin.orders.reason_required') : t('admin.orders.reason_optional')}
                        </Label>
                        <Textarea
                            id="status-change-reason"
                            value={reason}
                            onChange={(event) => setReason(event.target.value)}
                            maxLength={500}
                            required={pending === 'undo_ship'}
                        />
                    </div>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={busy}>{t('admin.orders.keep_as_is')}</AlertDialogCancel>
                        {pending === 'cancel' && paid ? (
                            <>
                                <AlertDialogAction variant="outline" onClick={() => confirm('later')} disabled={busy}>
                                    {t('admin.orders.cancel_refund_later')}
                                </AlertDialogAction>
                                <AlertDialogAction variant="destructive" onClick={() => confirm('now')} disabled={busy}>
                                    {t('admin.orders.cancel_refund_now', { amount: refundable })}
                                </AlertDialogAction>
                            </>
                        ) : (
                            <AlertDialogAction
                                variant={pending === 'cancel' ? 'destructive' : 'default'}
                                onClick={() => confirm()}
                                disabled={busy || reasonMissing}
                            >
                                {pending === 'cancel' ? t('admin.orders.cancel_order') : t('admin.orders.undo_ship')}
                            </AlertDialogAction>
                        )}
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

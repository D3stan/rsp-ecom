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
import { Card } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import useTranslation from '@/hooks/useTranslation';
import { router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useState } from 'react';

/** One refunds row as Admin\OrdersController::show sends it (Refund::toAdminArray). */
export interface OrderRefund {
    id: number;
    amount_cents: number;
    currency: string;
    reason: string | null;
    status: 'pending' | 'succeeded' | 'failed';
    source: 'admin' | 'stripe_dashboard';
    failure_message: string | null;
    created_by: { id: number; name: string } | null;
    created_at: string;
}

export interface RefundInfo {
    refundable_cents: number;
    has_payment_intent: boolean;
    /** order item id => units that can still go back in stock */
    restockable: Record<string, number>;
}

interface Line {
    id: number;
    name: string;
    quantity: number;
}

interface Props {
    orderId: number;
    currency: string;
    shippingAmount: number;
    lines: Line[];
    refunds: OrderRefund[];
    info: RefundInfo;
    formatDate: (date: string) => string;
}

const statusTone: Record<string, string> = {
    succeeded: 'bg-green-100 text-green-800',
    pending: 'bg-yellow-100 text-yellow-800',
    failed: 'bg-red-100 text-red-800',
};

/** Euro text of the form -> cents (null when not a positive amount with at most 2 decimals). */
export function eurosToCents(value: string): number | null {
    const normalized = value.trim().replace(',', '.');

    if (!/^\d+(\.\d{1,2})?$/.test(normalized)) return null;

    const cents = Math.round(parseFloat(normalized) * 100);

    return cents > 0 ? cents : null;
}

/**
 * Refunds of an order: what is still refundable, the refunds made so far (by an admin here or in the Stripe dashboard)
 * and the "Refund" dialog. The money moves through Stripe (Admin\OrdersController::refund -> RefundService); the
 * admin confirms the exact amount in an AlertDialog before anything is sent.
 */
export default function OrderRefunds({ orderId, currency, shippingAmount, lines, refunds, info, formatDate }: Props) {
    const { t } = useTranslation();
    const money = (cents: number, code = currency) =>
        new Intl.NumberFormat('it-IT', { style: 'currency', currency: code || 'EUR' }).format(cents / 100);

    const shippingCents = Math.round(shippingAmount * 100);
    const defaultAmount = (includeShipping: boolean) =>
        (
            (includeShipping || info.refundable_cents - shippingCents <= 0 ? info.refundable_cents : info.refundable_cents - shippingCents) / 100
        ).toFixed(2);

    const [open, setOpen] = useState(false);
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);
    const [retrying, setRetrying] = useState<number | null>(null);

    // Same Stripe request under the same idempotency key: safe, it can never pay twice
    const retry = (refundId: number) => {
        setRetrying(refundId);
        router.post(`/admin/orders/${orderId}/refunds/${refundId}/retry`, {}, { preserveScroll: true, onFinish: () => setRetrying(null) });
    };
    const [includeShipping, setIncludeShipping] = useState(true);
    const [amount, setAmount] = useState(defaultAmount(true));
    const [reason, setReason] = useState('');
    const [restock, setRestock] = useState<Record<number, number>>({});

    const cents = eurosToCents(amount);
    const amountValid = cents !== null && cents <= info.refundable_cents;
    const restockLines = lines.filter((line) => (info.restockable[line.id] ?? 0) > 0);
    const canRefund = info.has_payment_intent && info.refundable_cents > 0;

    const start = () => {
        setIncludeShipping(true);
        setAmount(defaultAmount(true));
        setReason('');
        setRestock({});
        setOpen(true);
    };

    const toggleShipping = (checked: boolean) => {
        setIncludeShipping(checked);
        setAmount(defaultAmount(checked));
    };

    const send = () => {
        if (!amountValid) return;

        setBusy(true);
        router.post(
            `/admin/orders/${orderId}/refund`,
            {
                amount: (cents! / 100).toFixed(2),
                reason: reason.trim() || null,
                restock: Object.fromEntries(Object.entries(restock).filter(([, qty]) => qty > 0)),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setConfirming(false);
                    setOpen(false);
                },
                onFinish: () => setBusy(false),
            },
        );
    };

    return (
        <Card className="space-y-4 p-6">
            <div className="flex items-center justify-between gap-2">
                <h2 className="flex items-center text-lg font-semibold">
                    <RotateCcw className="mr-2 h-5 w-5" />
                    {t('admin.orders.refunds.title')}
                </h2>
                {canRefund && (
                    <Button size="sm" variant="outline" onClick={start}>
                        {t('admin.orders.refunds.refund')}
                    </Button>
                )}
            </div>

            <p className="text-sm text-muted-foreground">
                {t('admin.orders.refunds.refundable', { amount: money(info.refundable_cents) })}
                {!info.has_payment_intent && ` ${t('admin.orders.refunds.no_payment_intent')}`}
            </p>

            {refunds.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('admin.orders.refunds.none')}</p>
            ) : (
                <ul className="space-y-3">
                    {refunds.map((refund) => (
                        <li key={refund.id} className="space-y-1 rounded-lg border p-3 text-sm">
                            <div className="flex items-center justify-between gap-2">
                                <span className="font-medium">{money(refund.amount_cents, refund.currency)}</span>
                                <Badge variant="secondary" className={statusTone[refund.status]}>
                                    {t(`admin.orders.refunds.status.${refund.status}`)}
                                </Badge>
                            </div>
                            <p className="text-muted-foreground">
                                {formatDate(refund.created_at)} ·{' '}
                                {refund.source === 'stripe_dashboard' ? t('admin.orders.refunds.source_dashboard') : (refund.created_by?.name ?? '—')}
                            </p>
                            {refund.reason && <p className="break-words text-muted-foreground italic">“{refund.reason}”</p>}
                            {refund.failure_message && <p className="break-words text-red-600">{refund.failure_message}</p>}
                            {refund.status === 'pending' && (
                                <div className="flex items-center justify-between gap-2 pt-1">
                                    <p className="text-muted-foreground">{t('admin.orders.refunds.pending_hint')}</p>
                                    <Button size="sm" variant="outline" onClick={() => retry(refund.id)} disabled={retrying !== null}>
                                        {t('admin.orders.refunds.retry')}
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <Dialog open={open && !confirming} onOpenChange={(next) => !busy && setOpen(next)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('admin.orders.refunds.dialog_title')}</DialogTitle>
                        <DialogDescription>{t('admin.orders.refunds.refundable', { amount: money(info.refundable_cents) })}</DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="refund-amount">{t('admin.orders.refunds.amount')}</Label>
                            <Input
                                id="refund-amount"
                                inputMode="decimal"
                                value={amount}
                                onChange={(event) => setAmount(event.target.value)}
                                aria-invalid={!amountValid}
                            />
                            {!amountValid && (
                                <p className="text-sm text-red-600">
                                    {t('admin.orders.refunds.amount_invalid', { max: money(info.refundable_cents) })}
                                </p>
                            )}
                        </div>

                        {shippingCents > 0 && (
                            <div className="space-y-1">
                                <div className="flex items-center gap-2">
                                    <Checkbox
                                        id="refund-shipping"
                                        checked={includeShipping}
                                        onCheckedChange={(checked) => toggleShipping(checked === true)}
                                    />
                                    <Label htmlFor="refund-shipping">
                                        {t('admin.orders.refunds.include_shipping', { amount: money(shippingCents) })}
                                    </Label>
                                </div>
                                <p className="text-sm text-muted-foreground">{t('admin.orders.refunds.shipping_hint')}</p>
                            </div>
                        )}

                        <div className="space-y-2">
                            <Label htmlFor="refund-reason">{t('admin.orders.reason_optional')}</Label>
                            <Textarea id="refund-reason" value={reason} onChange={(event) => setReason(event.target.value)} maxLength={500} />
                        </div>

                        {restockLines.length > 0 && (
                            <div className="space-y-2">
                                <p className="text-sm font-medium">{t('admin.orders.refunds.restock')}</p>
                                {restockLines.map((line) => (
                                    <div key={line.id} className="flex items-center justify-between gap-3">
                                        <Label htmlFor={`restock-${line.id}`} className="min-w-0 truncate font-normal">
                                            {line.name}
                                        </Label>
                                        <Input
                                            id={`restock-${line.id}`}
                                            type="number"
                                            min={0}
                                            max={info.restockable[line.id]}
                                            step={1}
                                            className="w-20"
                                            value={restock[line.id] ?? 0}
                                            onChange={(event) =>
                                                setRestock((prev) => ({
                                                    ...prev,
                                                    [line.id]: Math.min(info.restockable[line.id], Math.max(0, parseInt(event.target.value) || 0)),
                                                }))
                                            }
                                        />
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>

                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)}>
                            {t('admin.orders.keep_as_is')}
                        </Button>
                        <Button onClick={() => setConfirming(true)} disabled={!amountValid}>
                            {t('admin.orders.refunds.continue')}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <AlertDialog open={confirming} onOpenChange={(next) => !busy && setConfirming(next)}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('admin.orders.refunds.confirm_title', { amount: cents ? money(cents) : '' })}</AlertDialogTitle>
                        <AlertDialogDescription>{t('admin.orders.refunds.confirm_body')}</AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={busy}>{t('admin.orders.refunds.back')}</AlertDialogCancel>
                        <AlertDialogAction variant="destructive" onClick={send} disabled={busy || !amountValid}>
                            {t('admin.orders.refunds.confirm')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </Card>
    );
}

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
import useTranslation from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { Loader2 } from 'lucide-react';

import type { ShippingAddress } from './ShippingIssues';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
    processing: boolean;
    quote: { carrier_name: string; service_name: string; total_price: number; estimated_delivery?: string | null };
    providerName: string;
    // Y-m-d
    pickupDate: string;
    // what the customer paid for shipping minus the label price
    margin: number;
    address: ShippingAddress | null;
    // the provider's prepaid balance, when it can be read
    balance: number | null;
}

const euro = (amount: number) => `€${Number(amount).toFixed(2)}`;

// the price, pickup date and balance are what the admin is about to commit to
export default function BuyLabelDialog({
    open,
    onOpenChange,
    onConfirm,
    processing,
    quote,
    providerName,
    pickupDate,
    margin,
    address,
    balance,
}: Props) {
    const { t } = useTranslation();
    const pickupLabel = new Date(`${pickupDate}T00:00:00`).toLocaleDateString(undefined, {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    return (
        <AlertDialog open={open} onOpenChange={(next) => !processing && onOpenChange(next)}>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>{t('admin.orders.ship.confirm_buy_title')}</AlertDialogTitle>
                    <AlertDialogDescription asChild>
                        <div className="space-y-3 text-sm text-foreground">
                            <p className="font-medium">
                                {t('admin.orders.ship.confirm_buy_summary', {
                                    carrier: quote.carrier_name,
                                    service: quote.service_name,
                                    price: quote.total_price.toFixed(2),
                                })}
                            </p>
                            <p className={cn('text-xs', margin >= 0 ? 'text-emerald-700 dark:text-emerald-400' : 'text-red-700 dark:text-red-400')}>
                                {t('admin.orders.ship.confirm_margin', { amount: margin >= 0 ? `+${euro(margin)}` : `−${euro(Math.abs(margin))}` })}
                            </p>
                            <ul className="space-y-1">
                                <li>{t('admin.orders.ship.confirm_pickup', { date: pickupLabel })}</li>
                                {quote.estimated_delivery && <li>{t('admin.orders.ship.confirm_delivery', { date: quote.estimated_delivery })}</li>}
                                {balance !== null && (
                                    <li className={cn(balance < quote.total_price && 'font-medium text-destructive')}>
                                        {t('admin.orders.ship.confirm_balance', {
                                            provider: providerName,
                                            before: euro(balance),
                                            after: euro(balance - quote.total_price),
                                        })}
                                    </li>
                                )}
                            </ul>
                            {address && (
                                <div className="rounded-md border p-2 text-xs">
                                    <p className="font-medium">{t('admin.orders.ship.address')}</p>
                                    <p>
                                        {address.first_name} {address.last_name}
                                    </p>
                                    <p>{address.address_line_1}</p>
                                    <p>
                                        {address.postal_code} {address.city}
                                    </p>
                                </div>
                            )}
                            <p className="text-xs text-muted-foreground">{t('admin.orders.ship.email_on_pickup')}</p>
                        </div>
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>{t('admin.orders.ship.cancel')}</AlertDialogCancel>
                    <AlertDialogAction
                        disabled={processing}
                        onClick={(event) => {
                            // stay open (with a spinner) until the server answers
                            event.preventDefault();
                            onConfirm();
                        }}
                    >
                        {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                        {t('admin.orders.ship.confirm_buy_action', { price: quote.total_price.toFixed(2) })}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

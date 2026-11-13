import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import useTranslation from '@/hooks/useTranslation';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { AlertTriangle } from 'lucide-react';

/**
 * Shipping problems the admin must not miss (App\Shipping\Support\ShippingAlerts): a carrier account in test mode, the
 * last failed label purchase. Shown on every admin page until the server clears them (account live again, next label
 * bought), because a toast disappears before it is read.
 */
export default function ShippingAlertsBanner() {
    const { shippingAlerts = [] } = usePage<SharedData>().props;
    const { t } = useTranslation();

    if (shippingAlerts.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2 px-4 pt-4" role="region" aria-label={t('admin.shipping_alerts.region')}>
            {shippingAlerts.map((alert) => (
                <Alert key={`${alert.provider}:${alert.kind}`} variant="destructive">
                    <AlertTriangle className="h-4 w-4" />
                    <AlertTitle>
                        {alert.kind === 'test_mode'
                            ? t('admin.shipping_alerts.test_mode_title')
                            : t('admin.shipping_alerts.label_failed_title', { order: String(alert.context?.order_number ?? '') })}
                    </AlertTitle>
                    <AlertDescription>
                        <p>{alert.message}</p>
                        <p className="text-xs opacity-80">
                            {t('admin.shipping_alerts.since', { when: new Date(alert.at).toLocaleString() })}
                            {alert.kind === 'label_failed' && alert.context?.order_id ? (
                                <>
                                    {' · '}
                                    <Link href={`/admin/orders/${alert.context.order_id}`} className="underline">
                                        {t('admin.shipping_alerts.open_order')}
                                    </Link>
                                </>
                            ) : null}
                        </p>
                    </AlertDescription>
                </Alert>
            ))}
        </div>
    );
}

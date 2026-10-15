import { Card, CardContent } from '@/components/ui/card';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { Link, usePoll } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import { useEffect, useState } from 'react';

const POLL_EVERY_MS = 3000;
const POLL_FOR_MS = 60000;

interface PendingOrderNoticeProps {
    /** Amount paid, already formatted */
    total: string;
    /** Stripe checkout session id, for support */
    reference: string;
}

/**
 * Success page of a paid checkout whose order is not there yet (order.id "pending"): says the payment arrived, reloads
 * the order every 3 s for at most 60 s (the page renders the normal confirmation as soon as it exists), then stops
 * and points to the support address.
 */
export default function PendingOrderNotice({ total, reference }: PendingOrderNoticeProps) {
    const { t } = useTranslation();
    const supportEmail = useSetting('contact.support_email', '');
    const [timedOut, setTimedOut] = useState(false);

    // CheckoutController::success retries the order creation on every reload
    const { stop } = usePoll(POLL_EVERY_MS, { only: ['order', 'restricted', 'isGuest', 'session'] });

    useEffect(() => {
        const timer = setTimeout(() => {
            stop();
            setTimedOut(true);
        }, POLL_FOR_MS);

        return () => clearTimeout(timer);
    }, [stop]);

    return (
        <div className="min-h-screen bg-white">
            <div className="container mx-auto max-w-2xl px-4 py-8">
                <Card className="bg-white">
                    <CardContent className="space-y-4 py-8 text-center">
                        <div className="inline-flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                            <CheckCircle className="h-8 w-8 text-green-600" />
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900">{t('checkout.success.pending_title')}</h1>
                        <p className="text-gray-600">{t('checkout.success.pending_body')}</p>
                        <p className="text-sm text-gray-600">
                            {t('checkout.success.total')}: <span className="font-medium text-black">{total}</span>
                        </p>

                        {timedOut ? (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-left" role="status">
                                <p className="text-sm text-amber-900">{t('checkout.success.pending_timeout')}</p>
                                <p className="mt-2 text-sm">
                                    {supportEmail ? (
                                        <a href={`mailto:${supportEmail}`} className="font-medium text-amber-900 underline">
                                            {supportEmail}
                                        </a>
                                    ) : (
                                        <Link href={route('contact')} className="font-medium text-amber-900 underline">
                                            {t('checkout.success.pending_contact')}
                                        </Link>
                                    )}
                                </p>
                                <p className="mt-2 font-mono text-xs break-all text-amber-800">
                                    {t('checkout.success.pending_reference')}: {reference}
                                </p>
                            </div>
                        ) : (
                            <p className="flex items-center justify-center gap-2 text-sm text-gray-500" role="status">
                                <span className="h-4 w-4 rounded-full border-2 border-gray-300 border-t-gray-600 motion-safe:animate-spin" />
                                {t('checkout.success.pending_checking')}
                            </p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </div>
    );
}

import NewsletterSignup from '@/components/newsletter/NewsletterSignup';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link, router } from '@inertiajs/react';
import { CircleAlert, CircleCheck, LoaderCircle, MailCheck } from 'lucide-react';
import { useState } from 'react';

interface ConfirmProps {
    status: 'pending' | 'confirmed' | 'invalid';
    /** URL of this page; posting to it confirms */
    action: string;
}

/**
 * Target of the double opt-in link. Only the button confirms (a POST), so link scanners cannot.
 * An expired or used link offers to send a new one.
 */
export default function Confirm({ status, action }: ConfirmProps) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const title =
        status === 'pending'
            ? t('newsletter.confirm_email.title')
            : status === 'confirmed'
              ? t('newsletter.confirmed.title')
              : t('newsletter.invalid.title');

    const confirm = () => {
        router.post(action, {}, { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    };

    return (
        <PublicLayout>
            <Head title={title} />

            <div className="flex min-h-[70vh] items-center justify-center px-4 pt-24 pb-16">
                <Card className="w-full max-w-lg bg-white shadow-xl">
                    <CardContent className="space-y-6 p-8 text-center sm:p-10">
                        {status === 'pending' && <MailCheck className="mx-auto h-12 w-12 text-gray-700" aria-hidden="true" />}
                        {status === 'confirmed' && <CircleCheck className="mx-auto h-12 w-12 text-green-600" aria-hidden="true" />}
                        {status === 'invalid' && <CircleAlert className="mx-auto h-12 w-12 text-amber-600" aria-hidden="true" />}

                        <h1 className="text-2xl font-bold text-gray-900">{title}</h1>

                        {status === 'pending' && (
                            <>
                                <p className="text-gray-600">{t('newsletter.consent_v1')}</p>
                                <Button onClick={confirm} disabled={processing} className="bg-black text-white hover:bg-gray-800">
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    {t('newsletter.confirm_email.button')}
                                </Button>
                            </>
                        )}
                        {status === 'confirmed' && <p className="text-gray-600">{t('newsletter.confirmed.body')}</p>}
                        {status === 'invalid' && (
                            <>
                                <p className="text-gray-600">{t('newsletter.invalid.body')}</p>
                                <NewsletterSignup variant="light" className="text-left" />
                            </>
                        )}

                        <div>
                            <Button asChild variant="outline">
                                <Link href="/">{t('newsletter.back_to_shop')}</Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </PublicLayout>
    );
}

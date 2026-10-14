import NewsletterSignup from '@/components/newsletter/NewsletterSignup';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';
import { CircleAlert, CircleCheck } from 'lucide-react';

interface ConfirmProps {
    status: 'confirmed' | 'invalid';
}

/** Target of the double opt-in link. An expired or used link offers to send a new one. */
export default function Confirm({ status }: ConfirmProps) {
    const { t } = useTranslation();
    const confirmed = status === 'confirmed';
    const title = confirmed ? t('newsletter.confirmed.title') : t('newsletter.invalid.title');

    return (
        <PublicLayout>
            <Head title={title} />

            <div className="flex min-h-[70vh] items-center justify-center px-4 pt-24 pb-16">
                <Card className="w-full max-w-lg bg-white shadow-xl">
                    <CardContent className="space-y-6 p-8 text-center sm:p-10">
                        {confirmed ? (
                            <CircleCheck className="mx-auto h-12 w-12 text-green-600" aria-hidden="true" />
                        ) : (
                            <CircleAlert className="mx-auto h-12 w-12 text-amber-600" aria-hidden="true" />
                        )}
                        <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                        <p className="text-gray-600">{confirmed ? t('newsletter.confirmed.body') : t('newsletter.invalid.body')}</p>

                        {!confirmed && <NewsletterSignup variant="light" className="text-left" />}

                        <Button asChild variant="outline">
                            <Link href="/">{t('newsletter.back_to_shop')}</Link>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </PublicLayout>
    );
}

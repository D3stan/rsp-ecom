import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link, router } from '@inertiajs/react';
import { LoaderCircle, MailX } from 'lucide-react';
import { useState } from 'react';

interface UnsubscribeProps {
    done: boolean;
    email: string;
    /** the signed URL of this page; posting to it unsubscribes */
    action: string;
}

/** Page behind the signed unsubscribe link of every marketing email. */
export default function Unsubscribe({ done, email, action }: UnsubscribeProps) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const title = done ? t('newsletter.unsubscribe.done_title') : t('newsletter.unsubscribe.title');

    const unsubscribe = () => {
        router.post(action, {}, { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    };

    return (
        <PublicLayout>
            <Head title={title} />

            <div className="flex min-h-[70vh] items-center justify-center px-4 pt-24 pb-16">
                <Card className="w-full max-w-lg bg-white shadow-xl">
                    <CardContent className="space-y-6 p-8 text-center sm:p-10">
                        <MailX className="mx-auto h-12 w-12 text-gray-700" aria-hidden="true" />
                        <h1 className="text-2xl font-bold text-gray-900">{title}</h1>

                        {done ? (
                            <p className="text-gray-600">{t('newsletter.unsubscribe.done_body')}</p>
                        ) : (
                            <>
                                <p className="break-words text-gray-600">{t('newsletter.unsubscribe.question', { email })}</p>
                                <Button onClick={unsubscribe} disabled={processing} className="bg-black text-white hover:bg-gray-800">
                                    {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                                    {t('newsletter.unsubscribe.button')}
                                </Button>
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

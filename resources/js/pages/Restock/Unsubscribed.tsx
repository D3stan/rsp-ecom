import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import { Head, Link, router } from '@inertiajs/react';
import { Bell, BellOff, LinkIcon } from 'lucide-react';
import { useState } from 'react';

interface Props {
    status: 'confirm' | 'done' | 'invalid';
    /** Signed URL the button posts to (only while confirming) */
    actionUrl: string | null;
}

/**
 * Landing page of the unsubscribe link in back-in-stock emails (RestockUnsubscribeController). Opening the link
 * changes nothing (mail scanners prefetch links): the button posts to the same signed URL.
 */
export default function RestockUnsubscribed({ status, actionUrl }: Props) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    const content = {
        confirm: { icon: Bell, title: t('restock.confirm_title'), body: t('restock.confirm_body') },
        done: { icon: BellOff, title: t('restock.unsubscribed_title'), body: t('restock.unsubscribed_body') },
        invalid: { icon: LinkIcon, title: t('restock.invalid_title'), body: t('restock.invalid_body') },
    }[status];
    const Icon = content.icon;

    const unsubscribe = () => {
        if (!actionUrl) return;
        router.post(actionUrl, {}, { onStart: () => setProcessing(true), onFinish: () => setProcessing(false) });
    };

    return (
        <>
            <Head title={content.title} />
            <Header />

            <div className="min-h-screen bg-white">
                <div className="container mx-auto max-w-xl px-4 py-12">
                    <Card className="bg-white text-center">
                        <CardContent className="space-y-4 p-8">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                <Icon className="h-8 w-8 text-gray-600" />
                            </div>
                            <h1 className="text-2xl font-bold text-gray-900">{content.title}</h1>
                            <p className="text-gray-600">{content.body}</p>
                            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                {status === 'confirm' ? (
                                    <Button onClick={unsubscribe} disabled={processing}>
                                        {t('restock.confirm_button')}
                                    </Button>
                                ) : (
                                    <Button asChild>
                                        <Link href={route('profile.edit')}>{t('restock.manage_preferences')}</Link>
                                    </Button>
                                )}
                                <Button variant="outline" asChild>
                                    <Link href={route('home')}>{t('restock.back_home')}</Link>
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

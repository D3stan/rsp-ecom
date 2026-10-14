import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';
import { BellOff, LinkIcon } from 'lucide-react';

interface Props {
    unsubscribed: boolean;
}

/** Landing page of the unsubscribe link in back-in-stock emails (RestockUnsubscribeController). */
export default function RestockUnsubscribed({ unsubscribed }: Props) {
    const { t } = useTranslation();
    const title = unsubscribed ? t('restock.unsubscribed_title') : t('restock.invalid_title');
    const Icon = unsubscribed ? BellOff : LinkIcon;

    return (
        <>
            <Head title={title} />
            <Header />

            <div className="min-h-screen bg-white">
                <div className="container mx-auto max-w-xl px-4 py-12">
                    <Card className="bg-white text-center">
                        <CardContent className="space-y-4 p-8">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                <Icon className="h-8 w-8 text-gray-600" />
                            </div>
                            <h1 className="text-2xl font-bold text-gray-900">{title}</h1>
                            <p className="text-gray-600">{unsubscribed ? t('restock.unsubscribed_body') : t('restock.invalid_body')}</p>
                            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Button asChild>
                                    <Link href={route('profile.edit')}>{t('restock.manage_preferences')}</Link>
                                </Button>
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

import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import { Head, Link } from '@inertiajs/react';
import { LinkIcon } from 'lucide-react';

interface Props {
    supportEmail?: string | null;
}

export default function GuestOrderInvalid({ supportEmail }: Props) {
    const { t } = useTranslation();

    return (
        <>
            <Head title={t('guest_order.invalid_title')} />
            <Header />

            <div className="min-h-screen bg-white">
                <div className="container mx-auto max-w-xl px-4 py-12">
                    <Card className="bg-white text-center">
                        <CardContent className="space-y-4 p-8">
                            <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                                <LinkIcon className="h-8 w-8 text-gray-600" />
                            </div>
                            <h1 className="text-2xl font-bold text-gray-900">{t('guest_order.invalid_title')}</h1>
                            <p className="text-gray-600">{t('guest_order.invalid_body')}</p>
                            <div className="flex flex-col items-center justify-center gap-3 sm:flex-row">
                                <Button asChild>
                                    <Link href={route('contact')}>{t('guest_order.contact_us')}</Link>
                                </Button>
                                <Button variant="outline" asChild>
                                    <Link href={route('home')}>{t('guest_order.back_home')}</Link>
                                </Button>
                            </div>
                            {supportEmail && (
                                <p className="text-xs text-gray-500">
                                    <a href={`mailto:${supportEmail}`} className="underline">
                                        {supportEmail}
                                    </a>
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

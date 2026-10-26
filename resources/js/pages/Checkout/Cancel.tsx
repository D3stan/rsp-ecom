import Header from '@/components/header';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import { SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { HelpCircle, ShoppingCart, User, XCircle } from 'lucide-react';

interface Props {
    message?: string;
}

export default function CheckoutCancel({ message }: Props) {
    const { auth } = usePage().props as unknown as SharedData;
    const { t } = useTranslation();
    const isGuest = !auth.user;

    return (
        <>
            <Head title={t('checkout.cancel.head_title')} />
            <Header />

            <div className="min-h-screen bg-white">
                <div className="container mx-auto max-w-2xl px-4 py-8">
                    {/* Cancel Header */}
                    <div className="mb-8 text-center">
                        <div className="mb-4 inline-flex h-16 w-16 items-center justify-center rounded-full bg-gray-100">
                            <XCircle className="h-8 w-8 text-gray-600" />
                        </div>
                        <h1 className="mb-2 text-3xl font-bold text-gray-900">{t('checkout.cancel.title')}</h1>
                        <p className="text-gray-600">{message || t('checkout.cancel.default_message')}</p>
                    </div>

                    {/* Information Card */}
                    <Card className="mb-8 bg-white">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-gray-900">
                                <HelpCircle className="h-5 w-5" />
                                {t('checkout.cancel.what_happened')}
                            </CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="space-y-4">
                                <Alert className="border-yellow-200 bg-yellow-50">
                                    <AlertDescription className="text-gray-700">{t('checkout.cancel.reasons_intro')}</AlertDescription>
                                </Alert>

                                <ul className="ml-4 space-y-2 text-sm text-gray-600">
                                    <li className="flex items-start gap-2">
                                        <span className="text-gray-400">•</span>
                                        {t('checkout.cancel.reason_cancelled')}
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-gray-400">•</span>
                                        {t('checkout.cancel.reason_expired')}
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-gray-400">•</span>
                                        {t('checkout.cancel.reason_technical')}
                                    </li>
                                    <li className="flex items-start gap-2">
                                        <span className="text-gray-400">•</span>
                                        {t('checkout.cancel.reason_closed')}
                                    </li>
                                </ul>

                                <div className="rounded-lg border border-blue-200 bg-blue-50 p-4">
                                    <p className="text-sm text-blue-800">
                                        <strong>{t('checkout.cancel.good_news_label')}</strong> {t('checkout.cancel.good_news_body')}
                                    </p>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Action Buttons */}
                    <div className="space-y-4">
                        <Button variant="outline" asChild size="lg" className="w-full">
                            <Link href={route('checkout')} className="flex items-center gap-2">
                                <ShoppingCart className="h-4 w-4" />
                                {t('checkout.cancel.try_again')}
                            </Link>
                        </Button>

                        <Button variant="ghost" asChild className="w-full border-2 border-gray-300">
                            <Link href={route('products')} className="text-gray-600">
                                {t('checkout.cancel.continue_shopping')}
                            </Link>
                        </Button>

                        {/* Guest account creation prompt */}
                        {isGuest && (
                            <div className="border-t pt-4">
                                <div className="rounded-lg border border-green-200 bg-green-50 p-4">
                                    <div className="mb-2 flex items-center gap-2">
                                        <User className="h-4 w-4 text-green-600" />
                                        <h4 className="font-medium text-green-800">{t('checkout.cancel.create_account_title')}</h4>
                                    </div>
                                    <p className="mb-3 text-sm text-green-700">{t('checkout.cancel.create_account_body')}</p>
                                    <Button variant="outline" size="sm" asChild className="border-green-300 bg-white">
                                        <Link href={route('register')} className="text-green-700">
                                            {t('checkout.cancel.sign_up')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Help Section */}
                    <div className="mt-8 border-t pt-8 text-center">
                        <h3 className="mb-2 font-medium text-gray-900">{t('checkout.cancel.need_help_title')}</h3>
                        <p className="mb-4 text-sm text-gray-600">{t('checkout.cancel.need_help_body')}</p>
                        <div className="flex justify-center space-x-4">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={route('contact')}>{t('checkout.cancel.contact_support')}</Link>
                            </Button>
                            {/* You could add a live chat button here */}
                        </div>
                    </div>

                    {/* Additional Information */}
                    <div className="mt-8 text-center">
                        <div className="rounded-lg bg-gray-50 p-4">
                            <h4 className="mb-2 font-medium text-gray-900">{t('checkout.cancel.payment_security_title')}</h4>
                            <p className="text-xs text-gray-600">{t('checkout.cancel.payment_security_body')}</p>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

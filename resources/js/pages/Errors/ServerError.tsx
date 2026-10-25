import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import PublicLayout from '@/layouts/public-layout';
import { isFeedbackAvailable, openFeedbackForm } from '@/lib/sentry';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, ArrowLeft, Home, RefreshCw } from 'lucide-react';

export default function ServerError({ requestId }: { requestId?: string | null }) {
    const { t } = useTranslation();

    const refreshPage = () => {
        window.location.reload();
    };

    return (
        <PublicLayout currentPage="home">
            <Head title={t('error_pages.500.head_title')} />

            <div className="-mt-16 flex min-h-screen items-center justify-center px-4 py-0 pt-16">
                <div className="mx-auto max-w-2xl text-center">
                    <Card className="bg-white py-2 shadow-xl">
                        <CardContent className="p-12">
                            {/* Error Icon */}
                            <div className="mb-8">
                                <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-50">
                                    <AlertTriangle className="h-12 w-12 text-red-600" />
                                </div>
                            </div>

                            {/* Error Message */}
                            <div className="mb-8 space-y-4">
                                <h2 className="text-3xl font-bold text-gray-900">{t('error_pages.500.title')}</h2>
                                <p className="text-lg leading-relaxed text-gray-600">{t('error_pages.500.message')}</p>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                                <Button onClick={refreshPage} size="lg" className="w-full border bg-white text-black hover:bg-gray-300 sm:w-auto">
                                    <RefreshCw className="mr-2 h-4 w-4" />
                                    {t('error_pages.500.try_again')}
                                </Button>

                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="w-full border-gray-300 text-white hover:bg-gray-50 hover:text-black sm:w-auto"
                                >
                                    <Link href="/">
                                        <Home className="mr-2 h-4 w-4" />
                                        {t('error_pages.500.go_home')}
                                    </Link>
                                </Button>

                                <Button
                                    variant="ghost"
                                    size="lg"
                                    onClick={() => window.history.back()}
                                    className="w-full text-gray-600 hover:bg-gray-100 hover:text-gray-900 sm:w-auto"
                                >
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    {t('error_pages.500.go_back')}
                                </Button>
                            </div>

                            {/* Contact Support */}
                            <div className="mt-12 border-t border-gray-200 pt-8">
                                <p className="mb-4 text-sm text-gray-600">{t('error_pages.500.persistent_problem')}</p>
                                <Link href="/contact" className="text-sm font-medium text-black transition-colors hover:text-gray-700">
                                    {t('error_pages.500.contact_support')}
                                </Link>
                                {isFeedbackAvailable() && (
                                    <div className="mt-4">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                openFeedbackForm(
                                                    {
                                                        title: t('error_pages.500.report_title'),
                                                        messageLabel: t('error_pages.500.report_message_label'),
                                                        submit: t('error_pages.500.report_submit'),
                                                        cancel: t('error_pages.500.report_cancel'),
                                                        success: t('error_pages.500.report_success'),
                                                    },
                                                    requestId,
                                                )
                                            }
                                            className="text-gray-600 hover:bg-gray-100 hover:text-gray-900"
                                        >
                                            {t('error_pages.500.report_button')}
                                        </Button>
                                    </div>
                                )}
                                {requestId && (
                                    <p className="mt-4 text-xs text-gray-500">
                                        {t('error_pages.500.reference')}: <span className="font-mono select-all">{requestId}</span>
                                    </p>
                                )}
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PublicLayout>
    );
}

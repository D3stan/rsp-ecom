import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import AuthLayout from '@/layouts/auth-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, Clock, Mail, ShieldCheck } from 'lucide-react';

export default function EmailVerificationRequired() {
    const { t } = useTranslation();
    const handleResendVerification = () => {
        // This will be handled by the existing email verification notification system
        router.post(route('verification.send'));
    };

    return (
        <AuthLayout title={t('auth.required.title')} description={t('auth.required.description')}>
            <Head title={t('auth.required.head')} />

            <Card className="mx-auto w-full max-w-md">
                <CardHeader className="space-y-4 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-amber-50">
                        <ShieldCheck className="h-8 w-8 text-amber-600" />
                    </div>
                    <div className="space-y-2">
                        <CardTitle className="text-2xl font-semibold">{t('auth.required.title')}</CardTitle>
                        <CardDescription className="text-base">{t('auth.required.card_description')}</CardDescription>
                    </div>
                </CardHeader>

                <CardContent className="space-y-6">
                    <div className="space-y-4">
                        <div className="flex items-start space-x-3">
                            <Mail className="mt-0.5 h-5 w-5 flex-shrink-0 text-blue-600" />
                            <div>
                                <h3 className="text-sm font-medium">{t('auth.check_email')}</h3>
                                <p className="text-sm text-gray-600">{t('auth.required.step_email')}</p>
                            </div>
                        </div>

                        <div className="flex items-start space-x-3">
                            <Clock className="mt-0.5 h-5 w-5 flex-shrink-0 text-amber-600" />
                            <div>
                                <h3 className="text-sm font-medium">{t('auth.required.step_click_title')}</h3>
                                <p className="text-sm text-gray-600">{t('auth.required.step_click')}</p>
                            </div>
                        </div>

                        <div className="flex items-start space-x-3">
                            <ArrowRight className="mt-0.5 h-5 w-5 flex-shrink-0 text-green-600" />
                            <div>
                                <h3 className="text-sm font-medium">{t('auth.required.step_shop_title')}</h3>
                                <p className="text-sm text-gray-600">{t('auth.required.step_shop')}</p>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <Button onClick={handleResendVerification} className="w-full">
                            {t('auth.resend_verification')}
                        </Button>

                        <Link href={route('dashboard')}>
                            <Button variant="outline" className="w-full">
                                {t('auth.required.return_dashboard')}
                            </Button>
                        </Link>
                    </div>

                    <div className="border-t pt-4 text-center">
                        <p className="text-xs text-gray-500">{t('auth.required.trouble')}</p>
                    </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}

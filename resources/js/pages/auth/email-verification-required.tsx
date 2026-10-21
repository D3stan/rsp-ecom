import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Mail, ShieldCheck, Clock, ArrowRight } from 'lucide-react';
import useTranslation from '@/hooks/useTranslation';
import AuthLayout from '@/layouts/auth-layout';

export default function EmailVerificationRequired() {
    const { t } = useTranslation();
    const handleResendVerification = () => {
        // This will be handled by the existing email verification notification system
        router.post(route('verification.send'));
    };

    return (
        <AuthLayout
            title={t('auth.required.title')}
            description={t('auth.required.description')}
        >
            <Head title={t('auth.required.head')} />
            
            <Card className="w-full max-w-md mx-auto">
                <CardHeader className="text-center space-y-4">
                    <div className="mx-auto w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center">
                        <ShieldCheck className="w-8 h-8 text-amber-600" />
                    </div>
                    <div className="space-y-2">
                        <CardTitle className="text-2xl font-semibold">{t('auth.required.title')}</CardTitle>
                        <CardDescription className="text-base">
                            {t('auth.required.card_description')}
                        </CardDescription>
                    </div>
                </CardHeader>

                <CardContent className="space-y-6">
                    <div className="space-y-4">
                        <div className="flex items-start space-x-3">
                            <Mail className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h3 className="font-medium text-sm">{t('auth.check_email')}</h3>
                                <p className="text-sm text-gray-600">
                                    {t('auth.required.step_email')}
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-start space-x-3">
                            <Clock className="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h3 className="font-medium text-sm">{t('auth.required.step_click_title')}</h3>
                                <p className="text-sm text-gray-600">
                                    {t('auth.required.step_click')}
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-start space-x-3">
                            <ArrowRight className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h3 className="font-medium text-sm">{t('auth.required.step_shop_title')}</h3>
                                <p className="text-sm text-gray-600">
                                    {t('auth.required.step_shop')}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <Button
                            onClick={handleResendVerification}
                            className="w-full"
                        >
                            {t('auth.resend_verification')}
                        </Button>

                        <Link href={route('dashboard')}>
                            <Button variant="outline" className="w-full">
                                {t('auth.required.return_dashboard')}
                            </Button>
                        </Link>
                    </div>

                    <div className="text-center pt-4 border-t">
                        <p className="text-xs text-gray-500">
                            {t('auth.required.trouble')}
                        </p>
                    </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}

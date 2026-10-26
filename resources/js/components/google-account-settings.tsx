import { type SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/react';

import GoogleButton from '@/components/google-button';
import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';

export default function GoogleAccountSettings() {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user!;
    const { t } = useTranslation();

    const { post, processing } = useForm();

    const handleUnlinkGoogle = () => {
        if (confirm(t('account.google.unlink_confirm'))) {
            post(route('auth.google.unlink'));
        }
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title={t('account.google.heading')} description={t('account.google.heading_desc')} />

            <Card>
                <CardHeader className="pb-4">
                    <CardTitle className="text-base">{t('account.google.card_title')}</CardTitle>
                </CardHeader>
                <CardContent>
                    {user.google_id ? (
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-blue-50">
                                    <svg className="h-5 w-5" viewBox="0 0 24 24">
                                        <path
                                            fill="#4285F4"
                                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                        />
                                        <path
                                            fill="#34A853"
                                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                        />
                                        <path
                                            fill="#FBBC05"
                                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                        />
                                        <path
                                            fill="#EA4335"
                                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <p className="font-medium">{t('account.google.provider')}</p>
                                    <p className="text-sm text-muted-foreground">{t('account.google.connected_to', { email: user.email })}</p>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" onClick={handleUnlinkGoogle} disabled={processing || !user.has_password}>
                                {t('account.google.unlink')}
                            </Button>
                        </div>
                    ) : (
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-gray-50">
                                    <svg className="h-5 w-5 text-gray-400" viewBox="0 0 24 24">
                                        <path
                                            fill="currentColor"
                                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                                        />
                                        <path
                                            fill="currentColor"
                                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                                        />
                                        <path
                                            fill="currentColor"
                                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                                        />
                                        <path
                                            fill="currentColor"
                                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                                        />
                                    </svg>
                                </div>
                                <div>
                                    <p className="font-medium">{t('account.google.provider')}</p>
                                    <p className="text-sm text-muted-foreground">{t('account.google.not_connected')}</p>
                                </div>
                            </div>
                            <GoogleButton className="w-auto px-4">{t('account.google.connect')}</GoogleButton>
                        </div>
                    )}

                    {user.google_id && !user.has_password && (
                        <div className="mt-4 rounded-md border border-amber-200 bg-amber-50 p-3">
                            <p className="text-sm text-amber-800">
                                <strong>{t('account.google.note_label')}</strong> {t('account.google.note_body')}
                            </p>
                        </div>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}

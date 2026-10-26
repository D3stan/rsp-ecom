import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import AuthLayout from '@/layouts/auth-layout';
import { Head, useForm } from '@inertiajs/react';
import { AlertCircle, CheckCircle, Clock, Loader2, Mail } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

interface Props {
    email?: string;
    status?: string;
}

export default function VerificationPending({ email, status }: Props) {
    const { t } = useTranslation();
    const [timeLeft, setTimeLeft] = useState<number | null>(null);
    const [canResend, setCanResend] = useState(true);
    const [isPolling, setIsPolling] = useState(true);
    const [verificationStatus, setVerificationStatus] = useState<'pending' | 'verified' | 'expired' | 'error'>('pending');
    const [statusMessage, setStatusMessage] = useState<string>('');
    const pollIntervalRef = useRef<NodeJS.Timeout | null>(null);
    const isMountedRef = useRef(true);

    const { data, setData, post, processing, errors } = useForm({
        email: email || '',
    });

    // Cleanup function to stop polling
    const stopPolling = useCallback(() => {
        if (pollIntervalRef.current) {
            clearInterval(pollIntervalRef.current);
            pollIntervalRef.current = null;
        }
        setIsPolling(false);
    }, []);

    // Polling function to check verification status
    const checkVerificationStatus = useCallback(async () => {
        if (!email || !isMountedRef.current) return;

        try {
            // Get CSRF token from meta tag
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const response = await fetch(`/verification/status?email=${encodeURIComponent(email)}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...(token && { 'X-CSRF-TOKEN': token }),
                },
                credentials: 'same-origin',
            });

            // Check if response is HTML (error page) instead of JSON
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                console.error('Received non-JSON response:', contentType);
                // Try to get the HTML content for debugging
                const htmlContent = await response.text();
                console.error('HTML response:', htmlContent.substring(0, 200));
                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }

            const responseData = await response.json();

            // Only update state if component is still mounted
            if (!isMountedRef.current) return;

            if (responseData.verified) {
                setVerificationStatus('verified');
                setStatusMessage('Email verified successfully! Redirecting...');
                stopPolling();

                // If the user is now authenticated, redirect immediately
                // Otherwise, give a moment for the session to be established
                const redirectDelay = responseData.authenticated ? 1000 : 2000;

                setTimeout(() => {
                    if (isMountedRef.current) {
                        // Force a full page reload to ensure session is properly established
                        window.location.href = responseData.redirect_url || '/dashboard';
                    }
                }, redirectDelay);
            } else if (responseData.expired) {
                setVerificationStatus('expired');
                setStatusMessage(responseData.message || 'Verification link has expired.');
                stopPolling();
            } else if (responseData.error) {
                setVerificationStatus('error');
                setStatusMessage(responseData.error);
                stopPolling();
            }
            // If still pending, continue polling
        } catch (error) {
            console.error('Error checking verification status:', error);

            // Only update state if component is still mounted
            if (isMountedRef.current) {
                // On network errors, we'll continue polling but could show a temporary warning
                // For now, just log the error and continue
            }
        }
    }, [email, stopPolling]);

    // Set up polling when component mounts or email changes
    useEffect(() => {
        if (!email || !isPolling) return;

        // Initial check
        checkVerificationStatus();

        // Set up polling interval
        pollIntervalRef.current = setInterval(() => {
            if (isMountedRef.current && isPolling) {
                checkVerificationStatus();
            }
        }, 5000); // Poll every 5 seconds instead of 3

        // Cleanup function
        return () => {
            if (pollIntervalRef.current) {
                clearInterval(pollIntervalRef.current);
                pollIntervalRef.current = null;
            }
        };
    }, [email, isPolling, checkVerificationStatus]);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            isMountedRef.current = false;
            stopPolling();
        };
    }, [stopPolling]);

    // Handle page visibility changes to pause/resume polling
    useEffect(() => {
        const handleVisibilityChange = () => {
            if (document.hidden) {
                // Page is hidden, stop polling to save resources
                if (pollIntervalRef.current) {
                    clearInterval(pollIntervalRef.current);
                    pollIntervalRef.current = null;
                }
            } else {
                // Page is visible again, resume polling if still needed
                if (isPolling && !pollIntervalRef.current && verificationStatus === 'pending') {
                    checkVerificationStatus(); // Immediate check
                    pollIntervalRef.current = setInterval(checkVerificationStatus, 3000);
                }
            }
        };

        document.addEventListener('visibilitychange', handleVisibilityChange);

        return () => {
            document.removeEventListener('visibilitychange', handleVisibilityChange);
        };
    }, [isPolling, verificationStatus, checkVerificationStatus]);

    // Timer for rate limiting
    useEffect(() => {
        if (status === 'verification-link-sent') {
            setCanResend(false);
            setTimeLeft(60); // 60 seconds cooldown

            const timer = setInterval(() => {
                setTimeLeft((prev) => {
                    if (prev === null || prev <= 1) {
                        setCanResend(true);
                        clearInterval(timer);
                        return null;
                    }
                    return prev - 1;
                });
            }, 1000);

            return () => clearInterval(timer);
        }
    }, [status]);

    const handleResend = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('verification.resend.pending'));
    };

    return (
        <AuthLayout title={t('auth.pending.title')} description={t('auth.pending.description')}>
            <Head title={t('auth.pending.head')} />

            <Card className="mx-auto w-full max-w-md">
                <CardHeader className="space-y-4 text-center">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-blue-50">
                        <Mail className="h-8 w-8 text-blue-600" />
                    </div>
                    <div className="space-y-2">
                        <CardTitle className="text-2xl font-semibold">{t('auth.check_email')}</CardTitle>
                        <CardDescription className="text-base">{t('auth.pending.sent_to')}</CardDescription>
                        {email && <p className="rounded-md px-3 py-2 text-sm font-medium text-white">{email}</p>}
                    </div>
                </CardHeader>

                <CardContent className="space-y-4">
                    {verificationStatus === 'verified' && (
                        <Alert className="border-green-200 bg-green-50">
                            <CheckCircle className="h-4 w-4 text-green-600" />
                            <AlertDescription className="text-green-800">{statusMessage}</AlertDescription>
                        </Alert>
                    )}

                    {verificationStatus === 'expired' && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{statusMessage}</AlertDescription>
                        </Alert>
                    )}

                    {verificationStatus === 'error' && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{statusMessage}</AlertDescription>
                        </Alert>
                    )}

                    {status === 'verification-link-sent' && verificationStatus === 'pending' && (
                        <Alert>
                            <CheckCircle className="h-4 w-4" />
                            <AlertDescription>{t('auth.pending.link_resent')}</AlertDescription>
                        </Alert>
                    )}

                    {errors.email && (
                        <Alert variant="destructive">
                            <AlertCircle className="h-4 w-4" />
                            <AlertDescription>{errors.email}</AlertDescription>
                        </Alert>
                    )}

                    {verificationStatus === 'pending' && (
                        <div className="space-y-3">
                            <div className="flex items-center space-x-3 rounded-lg bg-blue-50 p-3">
                                <Loader2 className="h-4 w-4 animate-spin text-blue-600" />
                                <p className="text-sm text-blue-800">{t('auth.pending.waiting')}</p>
                            </div>
                        </div>
                    )}

                    <div className="space-y-3 text-sm text-gray-400">
                        <div className="flex items-start space-x-3">
                            <Clock className="mt-0.5 h-4 w-4 flex-shrink-0" />
                            <p>
                                {t('auth.pending.expiry_before')} <strong>{t('auth.pending.expiry_duration')}</strong>{' '}
                                {t('auth.pending.expiry_after')}
                            </p>
                        </div>
                        <div className="flex items-start space-x-3">
                            <CheckCircle className="mt-0.5 h-4 w-4 flex-shrink-0" />
                            <p>{t('auth.pending.tip_click')}</p>
                        </div>
                        <div className="flex items-start space-x-3">
                            <Mail className="mt-0.5 h-4 w-4 flex-shrink-0" />
                            <p>{t('auth.pending.tip_device')}</p>
                        </div>
                    </div>

                    {verificationStatus !== 'verified' && (
                        <div className="border-t pt-4">
                            <p className="mb-3 text-sm text-gray-500">{t('auth.pending.no_email')}</p>

                            <form onSubmit={handleResend} className="space-y-3">
                                <div>
                                    <Label htmlFor="email" className="sr-only">
                                        {t('auth.email_address')}
                                    </Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder={t('auth.pending.email_placeholder')}
                                        required
                                        disabled={processing || verificationStatus !== 'pending'}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    variant="outline"
                                    className="w-full bg-gray-200 text-black"
                                    disabled={processing || !canResend || verificationStatus !== 'pending'}
                                >
                                    {processing ? (
                                        <>
                                            <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                            {t('auth.pending.sending')}
                                        </>
                                    ) : !canResend && timeLeft ? (
                                        t('auth.pending.resend_in', { seconds: timeLeft })
                                    ) : (
                                        t('auth.resend_verification')
                                    )}
                                </Button>
                            </form>
                        </div>
                    )}
                </CardContent>

                <CardFooter className="flex-col space-y-2">
                    <p className="text-center text-xs text-gray-500">{t('auth.pending.support')}</p>
                </CardFooter>
            </Card>
        </AuthLayout>
    );
}

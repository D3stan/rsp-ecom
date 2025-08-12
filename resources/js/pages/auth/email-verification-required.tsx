import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Mail, ShieldCheck, Clock, ArrowRight } from 'lucide-react';
import AuthLayout from '@/layouts/auth-layout';

export default function EmailVerificationRequired() {
    const handleResendVerification = () => {
        // This will be handled by the existing email verification notification system
        window.location.href = route('verification.send');
    };

    return (
        <AuthLayout
            title="Email verification required"
            description="Please verify your email address to access this feature."
        >
            <Head title="Email Verification Required" />
            
            <Card className="w-full max-w-md mx-auto">
                <CardHeader className="text-center space-y-4">
                    <div className="mx-auto w-16 h-16 bg-amber-50 rounded-full flex items-center justify-center">
                        <ShieldCheck className="w-8 h-8 text-amber-600" />
                    </div>
                    <div className="space-y-2">
                        <CardTitle className="text-2xl font-semibold">Email verification required</CardTitle>
                        <CardDescription className="text-base">
                            You need to verify your email address to access this feature
                        </CardDescription>
                    </div>
                </CardHeader>

                <CardContent className="space-y-6">
                    <div className="space-y-4">
                        <div className="flex items-start space-x-3">
                            <Mail className="w-5 h-5 text-blue-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h3 className="font-medium text-sm">Check your email</h3>
                                <p className="text-sm text-gray-600">
                                    We've sent a verification link to your email address.
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-start space-x-3">
                            <Clock className="w-5 h-5 text-amber-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h3 className="font-medium text-sm">Click the link</h3>
                                <p className="text-sm text-gray-600">
                                    Follow the verification link in your email to complete the process.
                                </p>
                            </div>
                        </div>
                        
                        <div className="flex items-start space-x-3">
                            <ArrowRight className="w-5 h-5 text-green-600 mt-0.5 flex-shrink-0" />
                            <div>
                                <h3 className="font-medium text-sm">Start shopping</h3>
                                <p className="text-sm text-gray-600">
                                    Once verified, you'll have full access to checkout and account features.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="space-y-3">
                        <Button 
                            onClick={handleResendVerification}
                            className="w-full"
                        >
                            Resend verification email
                        </Button>
                        
                        <Link href={route('dashboard')}>
                            <Button variant="outline" className="w-full">
                                Return to dashboard
                            </Button>
                        </Link>
                    </div>

                    <div className="text-center pt-4 border-t">
                        <p className="text-xs text-gray-500">
                            Having trouble? Check your spam folder or contact support.
                        </p>
                    </div>
                </CardContent>
            </Card>
        </AuthLayout>
    );
}

import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import Header from '@/components/header';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { XCircle, ArrowLeft, ShoppingCart, HelpCircle, User } from 'lucide-react';

interface Props {
    message?: string;
}

export default function CheckoutCancel({ message }: Props) {
    const { auth } = usePage().props as any;
    const isGuest = !auth.user;

    return (
        <>
            <Head title="Checkout Cancelled" />
            <Header />
            
            <div className="container mx-auto px-4 py-8 max-w-2xl">
                {/* Cancel Header */}
                <div className="text-center mb-8">
                    <div className="inline-flex items-center justify-center w-16 h-16 bg-gray-100 rounded-full mb-4">
                        <XCircle className="h-8 w-8 text-gray-600" />
                    </div>
                    <h1 className="text-3xl font-bold text-gray-900 mb-2">Checkout Cancelled</h1>
                    <p className="text-gray-600">
                        {message || 'Your payment was cancelled. You can try again anytime.'}
                    </p>
                </div>

                {/* Information Card */}
                <Card className="mb-8">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <HelpCircle className="h-5 w-5" />
                            What Happened?
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="space-y-4">
                            <Alert>
                                <AlertDescription>
                                    Your payment was not processed. This could happen if:
                                </AlertDescription>
                            </Alert>
                            
                            <ul className="space-y-2 text-sm text-gray-600 ml-4">
                                <li className="flex items-start gap-2">
                                    <span className="text-gray-400">•</span>
                                    You cancelled the payment process
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-gray-400">•</span>
                                    Your payment session expired
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-gray-400">•</span>
                                    There was a technical issue
                                </li>
                                <li className="flex items-start gap-2">
                                    <span className="text-gray-400">•</span>
                                    You closed the payment window
                                </li>
                            </ul>

                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <p className="text-sm text-blue-800">
                                    <strong>Good news:</strong> Your cart items are still saved! 
                                    You can continue with your purchase at any time.
                                </p>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                {/* Action Buttons */}
                <div className="space-y-4">
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        {/* Try again button - uses generic checkout route that handles auth */}
                        <Button asChild size="lg" className="w-full">
                            <Link 
                                href={route('checkout')} 
                                className="flex items-center gap-2"
                            >
                                <ArrowLeft className="h-4 w-4" />
                                Try Again
                            </Link>
                        </Button>
                        
                        <Button variant="outline" asChild size="lg" className="w-full">
                            <Link href={route('cart')} className="flex items-center gap-2">
                                <ShoppingCart className="h-4 w-4" />
                                View Cart
                            </Link>
                        </Button>
                    </div>

                    <Button variant="ghost" asChild className="w-full">
                        <Link href={route('products')} className="text-gray-600">
                            Continue Shopping
                        </Link>
                    </Button>

                    {/* Guest account creation prompt */}
                    {isGuest && (
                        <div className="border-t pt-4">
                            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                <div className="flex items-center gap-2 mb-2">
                                    <User className="h-4 w-4 text-green-600" />
                                    <h4 className="font-medium text-green-800">Create an Account</h4>
                                </div>
                                <p className="text-sm text-green-700 mb-3">
                                    Save your cart items, track orders, and enjoy faster checkout.
                                </p>
                                <Button variant="outline" size="sm" asChild className="border-green-300">
                                    <Link href={route('register')} className="text-green-700">
                                        Sign Up Now
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    )}
                </div>

                {/* Help Section */}
                <div className="mt-8 pt-8 border-t text-center">
                    <h3 className="font-medium text-gray-900 mb-2">Need Help?</h3>
                    <p className="text-sm text-gray-600 mb-4">
                        If you're experiencing issues with checkout, we're here to help.
                    </p>
                    <div className="flex justify-center space-x-4">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={route('contact')}>
                                Contact Support
                            </Link>
                        </Button>
                        {/* You could add a live chat button here */}
                    </div>
                </div>

                {/* Additional Information */}
                <div className="mt-8 text-center">
                    <div className="bg-gray-50 rounded-lg p-4">
                        <h4 className="font-medium text-gray-900 mb-2">Payment Security</h4>
                        <p className="text-xs text-gray-600">
                            Your payment information is always secure. We use industry-standard encryption 
                            and never store your payment details on our servers.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

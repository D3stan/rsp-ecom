import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, LogIn, ShieldX } from 'lucide-react';

export default function Forbidden() {
    return (
        <PublicLayout currentPage="home">
            <Head title="403 Access Denied" />

            <div className="-mt-16 flex min-h-screen items-center justify-center px-4 py-0 pt-16">
                <div className="mx-auto max-w-2xl text-center">
                    <Card className="bg-white py-2 shadow-xl">
                        <CardContent className="p-12">
                            {/* 403 Icon */}
                            <div className="mb-8">
                                <div className="mx-auto flex h-24 w-24 items-center justify-center rounded-full bg-red-50">
                                    <ShieldX className="h-12 w-12 text-red-600" />
                                </div>
                            </div>

                            {/* Error Message */}
                            <div className="mb-8 space-y-4">
                                <h2 className="text-3xl font-bold text-gray-900">Access Denied</h2>
                                <p className="text-lg leading-relaxed text-gray-600">
                                    You don't have permission to access this resource. This area may be restricted to certain users or require special
                                    permissions.
                                </p>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                                <Button asChild size="lg" className="w-full border bg-white text-black hover:bg-gray-300 sm:w-auto">
                                    <Link href="/">
                                        <Home className="mr-2 h-4 w-4" />
                                        Go Home
                                    </Link>
                                </Button>

                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="w-full border-gray-300 text-white hover:bg-gray-50 hover:text-black sm:w-auto"
                                >
                                    <Link href="/login">
                                        <LogIn className="mr-2 h-4 w-4" />
                                        Sign In
                                    </Link>
                                </Button>

                                <Button
                                    variant="ghost"
                                    size="lg"
                                    onClick={() => window.history.back()}
                                    className="w-full text-gray-600 hover:bg-gray-100 hover:text-gray-900 sm:w-auto"
                                >
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    Go Back
                                </Button>
                            </div>

                            {/* Help Section */}
                            <div className="mt-12 border-t border-gray-200 pt-8">
                                <p className="mb-4 text-sm text-gray-600">Need help accessing this content?</p>
                                <Link href="/contact" className="text-sm font-medium text-black transition-colors hover:text-gray-700">
                                    Contact Support
                                </Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PublicLayout>
    );
}

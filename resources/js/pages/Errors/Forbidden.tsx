import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Home, ArrowLeft, ShieldX, LogIn } from 'lucide-react';

export default function Forbidden() {
    return (
        <PublicLayout currentPage="home">
            <Head title="403 Access Denied" />
            
            <div className="min-h-screen flex items-center justify-center px-4 -mt-16 pt-16 py-0">
                <div className="max-w-2xl mx-auto text-center">
                    <Card className="shadow-xl bg-white py-2">
                        <CardContent className="p-12">
                            {/* 403 Icon */}
                            <div className="mb-8">
                                <div className="mx-auto w-24 h-24 bg-red-50 rounded-full flex items-center justify-center">
                                    <ShieldX className="w-12 h-12 text-red-600" />
                                </div>
                            </div>
                            
                            {/* Error Message */}
                            <div className="mb-8 space-y-4">
                                <h2 className="text-3xl font-bold text-gray-900">
                                    Access Denied
                                </h2>
                                <p className="text-gray-600 text-lg leading-relaxed">
                                    You don't have permission to access this resource. 
                                    This area may be restricted to certain users or require special permissions.
                                </p>
                            </div>
                            
                            {/* Action Buttons */}
                            <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
                                <Button asChild size="lg" className="w-full sm:w-auto border bg-white text-black hover:bg-gray-300">
                                    <Link href="/">
                                        <Home className="w-4 h-4 mr-2" />
                                        Go Home
                                    </Link>
                                </Button>
                                
                                <Button asChild variant="outline" size="lg" className="w-full sm:w-auto border-gray-300 text-white hover:bg-gray-50 hover:text-black">
                                    <Link href="/login">
                                        <LogIn className="w-4 h-4 mr-2" />
                                        Sign In
                                    </Link>
                                </Button>
                                
                                <Button 
                                    variant="ghost" 
                                    size="lg" 
                                    onClick={() => window.history.back()}
                                    className="w-full sm:w-auto text-gray-600 hover:text-gray-900 hover:bg-gray-100"
                                >
                                    <ArrowLeft className="w-4 h-4 mr-2" />
                                    Go Back
                                </Button>
                            </div>
                            
                            {/* Help Section */}
                            <div className="mt-12 pt-8 border-t border-gray-200">
                                <p className="text-sm text-gray-600 mb-4">
                                    Need help accessing this content?
                                </p>
                                <Link 
                                    href="/contact" 
                                    className="text-black hover:text-gray-700 transition-colors text-sm font-medium"
                                >
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

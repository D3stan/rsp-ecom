import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Home, ArrowLeft, AlertTriangle, RefreshCw } from 'lucide-react';

export default function ServerError() {
    const refreshPage = () => {
        window.location.reload();
    };

    return (
        <PublicLayout currentPage="home">
            <Head title="500 Server Error" />
            
            <div className="min-h-screen flex items-center justify-center px-4 -mt-16 pt-16">
                <div className="max-w-2xl mx-auto text-center">
                    <Card className="shadow-xl border border-gray-200">
                        <CardContent className="p-12">
                            {/* Error Icon */}
                            <div className="mb-8">
                                <div className="mx-auto w-24 h-24 bg-red-50 rounded-full flex items-center justify-center">
                                    <AlertTriangle className="w-12 h-12 text-red-600" />
                                </div>
                            </div>
                            
                            {/* Error Message */}
                            <div className="mb-8 space-y-4">
                                <h2 className="text-3xl font-bold text-gray-900">
                                    Something Went Wrong
                                </h2>
                                <p className="text-gray-600 text-lg leading-relaxed">
                                    We're experiencing some technical difficulties. Our team has been notified 
                                    and is working to fix the issue. Please try again in a few moments.
                                </p>
                            </div>
                            
                            {/* Action Buttons */}
                            <div className="flex flex-col sm:flex-row gap-4 justify-center items-center">
                                <Button 
                                    onClick={refreshPage}
                                    size="lg" 
                                    className="w-full sm:w-auto bg-black text-white hover:bg-gray-900"
                                >
                                    <RefreshCw className="w-4 h-4 mr-2" />
                                    Try Again
                                </Button>
                                
                                <Button asChild variant="outline" size="lg" className="w-full sm:w-auto border-gray-300 text-gray-900 hover:bg-gray-50">
                                    <Link href="/">
                                        <Home className="w-4 h-4 mr-2" />
                                        Go Home
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
                            
                            {/* Contact Support */}
                            <div className="mt-12 pt-8 border-t border-gray-200">
                                <p className="text-sm text-gray-600 mb-4">
                                    If the problem persists, please contact our support team.
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

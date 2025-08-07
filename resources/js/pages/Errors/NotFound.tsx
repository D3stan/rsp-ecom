import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Home, ArrowLeft, Search, ShoppingBag } from 'lucide-react';

export default function NotFound() {
    return (
        <PublicLayout currentPage="home">
            <Head title="404 Not Found" />
            
            <div className="min-h-screen flex items-center justify-center px-4 -mt-16 pt-16 py-0">
                <div className="max-w-2xl mx-auto text-center">
                    <Card className="shadow-xl bg-white py-2">
                        <CardContent className="p-12">
                            {/* 404 Icon/Number */}
                            <div className="mb-8">
                                <h1 className="text-9xl font-bold text-gray-900 opacity-30 select-none">
                                    404
                                </h1>
                            </div>
                            
                            {/* Error Message */}
                            <div className="mb-8 space-y-4">
                                <h2 className="text-3xl font-bold text-gray-900">
                                    Page Not Found
                                </h2>
                                <p className="text-gray-600 text-lg leading-relaxed">
                                    Sorry, we couldn't find the page you're looking for. 
                                    The page might have been moved, deleted, or the URL might be incorrect.
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
                                    <Link href="/products">
                                        <ShoppingBag className="w-4 h-4 mr-2" />
                                        Shop Products
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
                                    Need help finding what you're looking for?
                                </p>
                                <div className="flex flex-col sm:flex-row gap-4 justify-center items-center text-sm">
                                    <Link 
                                        href="/contact" 
                                        className="text-black hover:text-gray-700 transition-colors font-medium"
                                    >
                                        Contact Support
                                    </Link>
                                    <span className="hidden sm:inline text-gray-400">•</span>
                                    <Link 
                                        href="/about" 
                                        className="text-black hover:text-gray-700 transition-colors font-medium"
                                    >
                                        About Us
                                    </Link>
                                    <span className="hidden sm:inline text-gray-400">•</span>
                                    <Link 
                                        href="/terms" 
                                        className="text-black hover:text-gray-700 transition-colors font-medium"
                                    >
                                        Terms of Service
                                    </Link>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </PublicLayout>
    );
}

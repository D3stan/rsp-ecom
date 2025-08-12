import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Home, ShoppingBag } from 'lucide-react';

export default function NotFound() {
    const { t } = useTranslation();
    return (
        <PublicLayout currentPage="home">
            <Head title="404 Not Found" />

            <div className="-mt-16 flex min-h-screen items-center justify-center px-4 py-0 pt-16">
                <div className="mx-auto max-w-2xl text-center">
                    <Card className="bg-white py-2 shadow-xl">
                        <CardContent className="p-12">
                            {/* 404 Icon/Number */}
                            <div className="mb-8">
                                <h1 className="text-9xl font-bold text-gray-900 opacity-30 select-none">404</h1>
                            </div>

                            {/* Error Message */}
                            <div className="mb-8 space-y-4">
                                <h2 className="text-3xl font-bold text-gray-900">{t('error_pages.404.title')}</h2>
                                <p className="text-lg leading-relaxed text-gray-600">{t('error_pages.404.message')}</p>
                            </div>

                            {/* Action Buttons */}
                            <div className="flex flex-col items-center justify-center gap-4 sm:flex-row">
                                <Button asChild size="lg" className="w-full border bg-white text-black hover:bg-gray-300 sm:w-auto">
                                    <Link href="/">
                                        <Home className="mr-2 h-4 w-4" />
                                        {t('error_pages.404.go_home')}
                                    </Link>
                                </Button>

                                <Button
                                    asChild
                                    variant="outline"
                                    size="lg"
                                    className="w-full border-gray-300 text-white hover:bg-gray-50 hover:text-black sm:w-auto"
                                >
                                    <Link href="/products">
                                        <ShoppingBag className="mr-2 h-4 w-4" />
                                        {t('error_pages.404.shop_products')}
                                    </Link>
                                </Button>

                                <Button
                                    variant="ghost"
                                    size="lg"
                                    onClick={() => window.history.back()}
                                    className="w-full text-gray-600 hover:bg-gray-100 hover:text-gray-900 sm:w-auto"
                                >
                                    <ArrowLeft className="mr-2 h-4 w-4" />
                                    {t('error_pages.404.go_back')}
                                </Button>
                            </div>

                            {/* Help Section */}
                            <div className="mt-12 border-t border-gray-200 pt-8">
                                <p className="mb-4 text-sm text-gray-600">{t('error_pages.404.need_help')}</p>
                                <div className="flex flex-col items-center justify-center gap-4 text-sm sm:flex-row">
                                    <Link href="/contact" className="font-medium text-black transition-colors hover:text-gray-700">
                                        {t('error_pages.404.contact_support')}
                                    </Link>
                                    <span className="hidden text-gray-400 sm:inline">•</span>
                                    <Link href="/about" className="font-medium text-black transition-colors hover:text-gray-700">
                                        {t('about_us')}
                                    </Link>
                                    <span className="hidden text-gray-400 sm:inline">•</span>
                                    <Link href="/terms" className="font-medium text-black transition-colors hover:text-gray-700">
                                        {t('terms_of_service')}
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

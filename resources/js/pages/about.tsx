import { Head, Link } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';
import useTranslation from '@/hooks/useTranslation';

export default function About() {
    const { t } = useTranslation();
    return (
        <PublicLayout currentPage="about">
            <Head title="About Us - Your Store" />
            
            {/* Main Content */}
            <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div className="text-center mb-16">
                    <h1 className="text-4xl font-bold text-black mb-6">{t('about.title')}</h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        {t('about.subtitle')}
                    </p>
                </div>

                <div className="grid md:grid-cols-2 gap-12 mb-16">
                    <div>
                        <h2 className="text-2xl font-bold text-black mb-4">{t('about.mission_title')}</h2>
                        <p className="text-gray-600 mb-6">
                            {t('about.mission_text_1')}
                        </p>
                        <p className="text-gray-600">
                            {t('about.mission_text_2')}
                        </p>
                    </div>
                    <div>
                        <h2 className="text-2xl font-bold text-black mb-4">{t('about.why_choose_title')}</h2>
                        <ul className="space-y-3 text-gray-600">
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                {t('about.curated_selection')}
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                {t('about.competitive_pricing')}
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                {t('about.fast_shipping')}
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                {t('about.return_policy')}
                            </li>
                            <li className="flex items-start">
                                <span className="font-semibold text-black mr-2">•</span>
                                {t('about.customer_support')}
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="text-center">
                    <h2 className="text-2xl font-bold text-black mb-4">{t('about.ready_to_shop')}</h2>
                    <p className="text-gray-600 mb-8">
                        {t('about.explore_cta')}
                    </p>
                    <Link href="/">
                        <Button size="lg" className="bg-black text-white hover:bg-gray-900">{t('about.explore_products')}</Button>
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}

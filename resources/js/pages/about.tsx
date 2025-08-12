import { Button } from '@/components/ui/button';
import useTranslation from '@/hooks/useTranslation';
import PublicLayout from '@/layouts/public-layout';
import { Head, Link } from '@inertiajs/react';

export default function About() {
    const { t } = useTranslation();
    return (
        <PublicLayout currentPage="about">
            <Head title="About Us - Your Store" />

            {/* Main Content */}
            <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="mb-16 text-center">
                    <h1 className="mb-6 text-4xl font-bold text-black">{t('about.title')}</h1>
                    <p className="mx-auto max-w-3xl text-xl text-gray-600">{t('about.subtitle')}</p>
                </div>

                <div className="mb-16 grid gap-12 md:grid-cols-2">
                    <div>
                        <h2 className="mb-4 text-2xl font-bold text-black">{t('about.mission_title')}</h2>
                        <p className="mb-6 text-gray-600">{t('about.mission_text_1')}</p>
                        <p className="text-gray-600">{t('about.mission_text_2')}</p>
                    </div>
                    <div>
                        <h2 className="mb-4 text-2xl font-bold text-black">{t('about.why_choose_title')}</h2>
                        <ul className="space-y-3 text-gray-600">
                            <li className="flex items-start">
                                <span className="mr-2 font-semibold text-black">•</span>
                                {t('about.curated_selection')}
                            </li>
                            <li className="flex items-start">
                                <span className="mr-2 font-semibold text-black">•</span>
                                {t('about.competitive_pricing')}
                            </li>
                            <li className="flex items-start">
                                <span className="mr-2 font-semibold text-black">•</span>
                                {t('about.fast_shipping')}
                            </li>
                            <li className="flex items-start">
                                <span className="mr-2 font-semibold text-black">•</span>
                                {t('about.return_policy')}
                            </li>
                            <li className="flex items-start">
                                <span className="mr-2 font-semibold text-black">•</span>
                                {t('about.customer_support')}
                            </li>
                        </ul>
                    </div>
                </div>

                <div className="text-center">
                    <h2 className="mb-4 text-2xl font-bold text-black">{t('about.ready_to_shop')}</h2>
                    <p className="mb-8 text-gray-600">{t('about.explore_cta')}</p>
                    <Link href="/">
                        <Button size="lg" className="bg-black text-white hover:bg-gray-900">
                            {t('about.explore_products')}
                        </Button>
                    </Link>
                </div>
            </div>
        </PublicLayout>
    );
}

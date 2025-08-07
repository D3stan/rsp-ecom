import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import { useTranslation } from '@/hooks/useTranslation';

export default function Terms() {
    const { t } = useTranslation();
    
    return (
        <>
            <Head title={`${t('terms.title')} - Your Store`} />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">{t('terms.title')}</h1>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-8">
                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.acceptance')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.acceptance_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.use_license')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.use_license_text')}
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>{t('terms.modify_materials')}</li>
                                    <li>{t('terms.use_commercial')}</li>
                                    <li>{t('terms.reverse_engineer')}</li>
                                    <li>{t('terms.remove_copyright')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.disclaimer')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.disclaimer_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.limitations')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.limitations_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.accuracy')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.accuracy_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.links')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.links_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.modifications')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.modifications_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('terms.governing_law')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.governing_law_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('contact.contact_us')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('terms.contact_terms')}
                                </p>
                                <ul className="list-none text-gray-600 space-y-2">
                                    <li>{t('terms.contact_email')}</li>
                                </ul>
                            </section>
                        </div>
                    </div>

                    <div className="text-center mt-12 pt-8 border-t border-gray-200">
                        <p className="text-sm text-gray-500">
                            {t('last_updated')}: {new Date().toLocaleDateString()}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
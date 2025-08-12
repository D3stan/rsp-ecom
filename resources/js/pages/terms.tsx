import Header from '@/components/header';
import { useTranslation } from '@/hooks/useTranslation';
import { Head } from '@inertiajs/react';

export default function Terms() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${t('terms.title')} - Your Store`} />

            <div className="min-h-screen bg-white">
                <Header />

                <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <h1 className="mb-6 text-4xl font-bold text-black">{t('terms.title')}</h1>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-8">
                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.acceptance')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.acceptance_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.use_license')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.use_license_text')}</p>
                                <ul className="list-inside list-disc space-y-2 text-gray-600">
                                    <li>{t('terms.modify_materials')}</li>
                                    <li>{t('terms.use_commercial')}</li>
                                    <li>{t('terms.reverse_engineer')}</li>
                                    <li>{t('terms.remove_copyright')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.disclaimer')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.disclaimer_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.limitations')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.limitations_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.accuracy')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.accuracy_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.links')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.links_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.modifications')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.modifications_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('terms.governing_law')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.governing_law_text')}</p>
                            </section>

                            <section>
                                <h2 className="mb-4 text-2xl font-bold text-black">{t('contact.contact_us')}</h2>
                                <p className="mb-4 text-gray-600">{t('terms.contact_terms')}</p>
                                <ul className="list-none space-y-2 text-gray-600">
                                    <li>{t('terms.contact_email')}</li>
                                </ul>
                            </section>
                        </div>
                    </div>

                    <div className="mt-12 border-t border-gray-200 pt-8 text-center">
                        <p className="text-sm text-gray-500">
                            {t('last_updated')}: {new Date().toLocaleDateString()}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}

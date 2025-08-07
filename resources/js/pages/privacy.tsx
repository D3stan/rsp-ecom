import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import { useTranslation } from '@/hooks/useTranslation';

export default function Privacy() {
    const { t } = useTranslation();

    return (
        <>
            <Head title={`${t('privacy.title')} - Your Store`} />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">{t('privacy.title')}</h1>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-8">
                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('privacy.info_collect')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.info_collect_text')}
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>{t('privacy.personal_info')}</li>
                                    <li>{t('privacy.billing_shipping')}</li>
                                    <li>{t('privacy.payment_info')}</li>
                                    <li>{t('privacy.order_history')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('privacy.how_we_use')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.how_we_use_text')}
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>{t('privacy.process_orders')}</li>
                                    <li>{t('privacy.send_confirmations')}</li>
                                    <li>{t('privacy.provide_support')}</li>
                                    <li>{t('privacy.improve_services')}</li>
                                    <li>{t('privacy.send_promotional')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('privacy.info_sharing')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.info_sharing_text')}
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>{t('privacy.service_providers')}</li>
                                    <li>{t('privacy.required_by_law')}</li>
                                    <li>{t('privacy.explicit_consent')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('privacy.data_security')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.data_security_text')}
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>{t('privacy.ssl_encryption')}</li>
                                    <li>{t('privacy.secure_payment')}</li>
                                    <li>{t('privacy.security_audits')}</li>
                                    <li>{t('privacy.limited_access')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('privacy.your_rights')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.your_rights_text')}
                                </p>
                                <ul className="list-disc list-inside text-gray-600 space-y-2">
                                    <li>{t('privacy.access_update')}</li>
                                    <li>{t('privacy.request_deletion')}</li>
                                    <li>{t('privacy.opt_out')}</li>
                                    <li>{t('privacy.request_copy')}</li>
                                </ul>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('privacy.cookies')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.cookies_text')}
                                </p>
                            </section>

                            <section>
                                <h2 className="text-2xl font-bold text-black mb-4">{t('contact.contact_us')}</h2>
                                <p className="text-gray-600 mb-4">
                                    {t('privacy.contact_privacy')}
                                </p>
                                <ul className="list-none text-gray-600 space-y-2">
                                    <li>{t('privacy.contact_email')}</li>
                                </ul>
                            </section>
                        </div>
                    </div>

                    <div className="text-center mt-12 pt-8 border-t border-gray-200">
                        <p className="text-sm text-gray-500">
                            Last updated: {new Date().toLocaleDateString()}
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
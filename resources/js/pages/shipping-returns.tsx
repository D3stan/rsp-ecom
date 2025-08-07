import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import useTranslation from '@/hooks/useTranslation';

export default function ShippingReturns() {
    const { t } = useTranslation();
    return (
        <>
            <Head title="Shipping & Returns - Your Store" />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">{t('shipping_returns.title')}</h1>
                        <p className="text-lg text-gray-600">
                            {t('shipping_returns.subtitle')}
                        </p>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-12">
                            {/* Shipping Policy Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('shipping_returns.shipping_policy')}</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.shipping_destinations')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.shipping_destinations_text')}
                                        </p>
                                        <p className="text-gray-600">
                                            {t('shipping_returns.uk_notice')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.carriers_methods')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.carriers_text')}
                                        </p>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <h4 className="font-semibold text-black mb-2">{t('shipping_returns.available_options')}</h4>
                                            <ul className="list-disc list-inside text-gray-600 space-y-1">
                                                <li>{t('shipping_returns.standard_delivery')}</li>
                                                <li>{t('shipping_returns.express_delivery')}</li>
                                                <li>{t('shipping_returns.tracked_delivery')}</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.shipping_costs')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.shipping_costs_text')}
                                        </p>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b border-gray-200">
                                                        <th className="text-left py-2 text-black">{t('shipping_returns.destination')}</th>
                                                        <th className="text-left py-2 text-black">{t('shipping_returns.standard')}</th>
                                                        <th className="text-left py-2 text-black">{t('shipping_returns.express')}</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="text-gray-600">
                                                    <tr>
                                                        <td className="py-1">{t('shipping_returns.eu_countries')}</td>
                                                        <td className="py-1">€4.99</td>
                                                        <td className="py-1">€9.99</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="py-1">{t('shipping_returns.uk')}</td>
                                                        <td className="py-1">€7.99</td>
                                                        <td className="py-1">€14.99</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.dispatch_delivery')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.dispatch_text')}
                                        </p>
                                        <p className="text-gray-600">
                                            {t('shipping_returns.risk_transfer')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.missing_damaged')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.missing_damaged_text')}
                                        </p>
                                        <p className="text-gray-600">
                                            {t('shipping_returns.contact_email')}
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Returns & Right of Withdrawal Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('shipping_returns.returns_withdrawal')}</h2>
                                
                                <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                                    <p className="text-blue-800 font-medium">
                                        <strong>Important:</strong> {t('shipping_returns.no_free_returns')}
                                    </p>
                                </div>

                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.eu_withdrawal')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.withdrawal_text')}
                                        </p>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>{t('shipping_returns.defective_product')}</li>
                                            <li>{t('shipping_returns.wrong_item')}</li>
                                            <li>{t('shipping_returns.damaged_shipping')}</li>
                                        </ul>
                                        <p className="text-gray-600">
                                            <strong>Note:</strong> {t('shipping_returns.no_change_mind')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.return_request')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.return_steps')}
                                        </p>
                                        <ol className="list-decimal list-inside text-gray-600 space-y-2 mb-4">
                                            <li>{t('shipping_returns.step_1')}</li>
                                            <li>{t('shipping_returns.step_2')}</li>
                                            <li>{t('shipping_returns.step_3')}</li>
                                            <li>{t('shipping_returns.step_4')}</li>
                                        </ol>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <p className="text-gray-600">
                                                <strong>Contact for Returns:</strong><br />
                                                {t('shipping_returns.contact_email')}<br />
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.return_shipping')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.return_shipping_text')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.condition_goods')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.condition_text')}
                                        </p>
                                        <p className="text-gray-600">
                                            {t('shipping_returns.partial_refunds')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.refund_processing')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.refund_text')}
                                        </p>
                                        <p className="text-gray-600">
                                            {t('shipping_returns.withhold_refund')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.exempt_items')}</h3>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2">
                                            <li>{t('shipping_returns.personalized_items')}</li>
                                            <li>{t('shipping_returns.hygiene_items')}</li>
                                            <li>{t('shipping_returns.perishable_goods')}</li>
                                            <li>{t('shipping_returns.digital_content')}</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('shipping_returns.dispute_resolution')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            {t('shipping_returns.dispute_text')}
                                        </p>
                                        <p className="text-gray-600">
                                            {t('shipping_returns.adr_contact')}
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div className="text-center mt-12 pt-8 border-t border-gray-200">
                        <p className="text-sm text-gray-500">
                            {t('last_updated')}: August 5, 2025
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
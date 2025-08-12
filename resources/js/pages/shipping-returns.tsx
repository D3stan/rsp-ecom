import Header from '@/components/header';
import useTranslation from '@/hooks/useTranslation';
import { Head } from '@inertiajs/react';

export default function ShippingReturns() {
    const { t } = useTranslation();
    return (
        <>
            <Head title="Shipping & Returns - Your Store" />

            <div className="min-h-screen bg-white">
                <Header />

                <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <h1 className="mb-6 text-4xl font-bold text-black">{t('shipping_returns.title')}</h1>
                        <p className="text-lg text-gray-600">{t('shipping_returns.subtitle')}</p>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-12">
                            {/* Shipping Policy Section */}
                            <section>
                                <h2 className="mb-6 text-3xl font-bold text-black">{t('shipping_returns.shipping_policy')}</h2>

                                <div className="space-y-6">
                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.shipping_destinations')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.shipping_destinations_text')}</p>
                                        <p className="text-gray-600">{t('shipping_returns.uk_notice')}</p>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.carriers_methods')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.carriers_text')}</p>
                                        <div className="rounded-lg bg-gray-50 p-4">
                                            <h4 className="mb-2 font-semibold text-black">{t('shipping_returns.available_options')}</h4>
                                            <ul className="list-inside list-disc space-y-1 text-gray-600">
                                                <li>{t('shipping_returns.standard_delivery')}</li>
                                                <li>{t('shipping_returns.express_delivery')}</li>
                                                <li>{t('shipping_returns.tracked_delivery')}</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.shipping_costs')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.shipping_costs_text')}</p>
                                        <div className="rounded-lg bg-gray-50 p-4">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b border-gray-200">
                                                        <th className="py-2 text-left text-black">{t('shipping_returns.destination')}</th>
                                                        <th className="py-2 text-left text-black">{t('shipping_returns.standard')}</th>
                                                        <th className="py-2 text-left text-black">{t('shipping_returns.express')}</th>
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
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.dispatch_delivery')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.dispatch_text')}</p>
                                        <p className="text-gray-600">{t('shipping_returns.risk_transfer')}</p>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.missing_damaged')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.missing_damaged_text')}</p>
                                        <p className="text-gray-600">{t('shipping_returns.contact_email')}</p>
                                    </div>
                                </div>
                            </section>

                            {/* Returns & Right of Withdrawal Section */}
                            <section>
                                <h2 className="mb-6 text-3xl font-bold text-black">{t('shipping_returns.returns_withdrawal')}</h2>

                                <div className="mb-6 border-l-4 border-blue-400 bg-blue-50 p-4">
                                    <p className="font-medium text-blue-800">
                                        <strong>Important:</strong> {t('shipping_returns.no_free_returns')}
                                    </p>
                                </div>

                                <div className="space-y-6">
                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.eu_withdrawal')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.withdrawal_text')}</p>
                                        <ul className="mb-4 list-inside list-disc space-y-2 text-gray-600">
                                            <li>{t('shipping_returns.defective_product')}</li>
                                            <li>{t('shipping_returns.wrong_item')}</li>
                                            <li>{t('shipping_returns.damaged_shipping')}</li>
                                        </ul>
                                        <p className="text-gray-600">
                                            <strong>Note:</strong> {t('shipping_returns.no_change_mind')}
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.return_request')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.return_steps')}</p>
                                        <ol className="mb-4 list-inside list-decimal space-y-2 text-gray-600">
                                            <li>{t('shipping_returns.step_1')}</li>
                                            <li>{t('shipping_returns.step_2')}</li>
                                            <li>{t('shipping_returns.step_3')}</li>
                                            <li>{t('shipping_returns.step_4')}</li>
                                        </ol>
                                        <div className="rounded-lg bg-gray-50 p-4">
                                            <p className="text-gray-600">
                                                <strong>Contact for Returns:</strong>
                                                <br />
                                                {t('shipping_returns.contact_email')}
                                                <br />
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.return_shipping')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.return_shipping_text')}</p>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.condition_goods')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.condition_text')}</p>
                                        <p className="text-gray-600">{t('shipping_returns.partial_refunds')}</p>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.refund_processing')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.refund_text')}</p>
                                        <p className="text-gray-600">{t('shipping_returns.withhold_refund')}</p>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.exempt_items')}</h3>
                                        <ul className="list-inside list-disc space-y-2 text-gray-600">
                                            <li>{t('shipping_returns.personalized_items')}</li>
                                            <li>{t('shipping_returns.hygiene_items')}</li>
                                            <li>{t('shipping_returns.perishable_goods')}</li>
                                            <li>{t('shipping_returns.digital_content')}</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <h3 className="mb-3 text-xl font-semibold text-black">{t('shipping_returns.dispute_resolution')}</h3>
                                        <p className="mb-3 text-gray-600">{t('shipping_returns.dispute_text')}</p>
                                        <p className="text-gray-600">{t('shipping_returns.adr_contact')}</p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div className="mt-12 border-t border-gray-200 pt-8 text-center">
                        <p className="text-sm text-gray-500">{t('last_updated')}: August 5, 2025</p>
                    </div>
                </div>
            </div>
        </>
    );
}

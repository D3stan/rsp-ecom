import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import useTranslation from '@/hooks/useTranslation';

export default function Terms() {
    const { t } = useTranslation();
    
    return (
        <>
            <Head title={`${t('terms_of_sale_warranty')} - Your Store`} />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">{t('terms_of_sale_warranty')}</h1>
                        <p className="text-lg text-gray-600">
                            {t('terms_legal_governing')}
                        </p>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-12">
                            {/* Contract Formation Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('contract_formation')}</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('offer_acceptance')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            Product displays on our website do not constitute binding offers. A legally binding 
                                            contract is formed only when we send you an <strong>"Order Confirmation"</strong> email 
                                            after you place your order.
                                        </p>
                                        <p className="text-gray-600">
                                            We reserve the right to refuse or cancel orders at our discretion, including 
                                            cases of pricing errors, product unavailability, or suspected fraudulent activity.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('languages')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            Our terms are available in English, Spanish, French, German, and Italian. 
                                            In case of discrepancies between language versions, the English version prevails.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('technical_steps')}</h3>
                                        <ol className="list-decimal list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Add products to your shopping basket</li>
                                            <li>Proceed to checkout and enter delivery/billing information</li>
                                            <li>Review your order on the confirmation screen</li>
                                            <li>Click "Confirm Order" to submit your order</li>
                                            <li>Receive order confirmation email with contract details</li>
                                        </ol>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('error_correction')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            You can correct errors using the "Edit" links or browser back button on the 
                                            order review screen before confirming your purchase.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('contract_archive')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            A PDF copy of your contract is sent via email and stored in your user account 
                                            for 12 months. You can access past orders through your account dashboard.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Prices & Payment Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('prices_payment')}</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('pricing')}</h3>
                                        <p className="text-gray-600 mb-3">
                                            All prices include VAT and applicable eco-fees. Shipping costs are shown 
                                            separately and added at checkout.
                                        </p>
                                        <p className="text-gray-600">
                                            Prices are subject to change without notice. The price applicable to your 
                                            order is the price displayed at the time of order confirmation.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">{t('accepted_payment_methods')}</h3>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Visa and Mastercard credit/debit cards</li>
                                            <li>SEPA Direct Debit</li>
                                            <li>PayPal</li>
                                            <li>Apple Pay</li>
                                            <li>Klarna Pay-Later (where available)</li>
                                        </ul>
                                        <p className="text-gray-600">
                                            All payments are processed securely through Stripe. We do not store your 
                                            payment information on our servers.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Currency & Conversion</h3>
                                        <p className="text-gray-600 mb-3">
                                            Prices are displayed in Euros (EUR). Currency conversion fees, if applicable, 
                                            are borne by the customer and charged by your payment provider.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Promotional Codes</h3>
                                        <p className="text-gray-600 mb-3">
                                            Promotional codes are single-use, non-stackable, and have no cash value. 
                                            Codes cannot be combined with other offers unless explicitly stated.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Delivery Conditions Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('delivery_conditions')}</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Title & Risk Transfer</h3>
                                        <p className="text-gray-600 mb-3">
                                            Title and risk of loss pass to you upon delivery to your specified address 
                                            or collection point.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Delivery Delays</h3>
                                        <p className="text-gray-600 mb-3">
                                            If we fail to deliver within the estimated timeframe, we will provide an 
                                            additional 14-day grace period. If delivery still fails, you have the right 
                                            to cancel your order for a full refund.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Mixed Orders</h3>
                                        <p className="text-gray-600 mb-3">
                                            We may hold orders containing multiple items until all items are available, 
                                            unless you specifically opt for split delivery during checkout.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Statutory Warranty Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('statutory_warranty')}</h2>
                                
                                <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                                    <p className="text-blue-800 font-medium">
                                        This warranty is provided by law and applies to all products sold to consumers in the EU.
                                    </p>
                                </div>

                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Duration</h3>
                                        <p className="text-gray-600 mb-3">
                                            <strong>2 years</strong> from delivery for all products across the EU.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Burden of Proof</h3>
                                        <p className="text-gray-600 mb-3">
                                            If a defect appears within <strong>1 year</strong> of delivery, it is presumed 
                                            to have existed at the time of delivery. We voluntarily extend this presumption 
                                            to the full 2-year warranty period.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Available Remedies</h3>
                                        <p className="text-gray-600 mb-3">
                                            You can choose between repair and replacement, unless one option is impossible 
                                            or disproportionately costly. If both repair and replacement fail or are 
                                            inappropriate, you may request:
                                        </p>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Price reduction (partial refund)</li>
                                            <li>Full refund (contract termination)</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Remedy Timeframe</h3>
                                        <p className="text-gray-600 mb-3">
                                            We will provide remedies within a reasonable period and without significant 
                                            inconvenience to you. Our target is to ship replacement items within 
                                            <strong>5 business days</strong> of approval.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">How to Claim Warranty</h3>
                                        <p className="text-gray-600 mb-3">
                                            Contact our customer service with your order number and description of the defect:
                                        </p>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <p className="text-gray-600">
                                                <strong>Warranty Claims:</strong><br />
                                                Email: <a href="mailto:warranty@yourstore.com" className="text-blue-600 hover:underline">warranty@yourstore.com</a><br />
                                                Phone: +39 06 1234 5678<br />
                                                Hours: Monday-Friday, 9:00-17:00 CET
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </section>

                            {/* Commercial Guarantee Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">{t('commercial_guarantee')}</h2>
                                
                                <div className="bg-green-50 border-l-4 border-green-400 p-4 mb-6">
                                    <p className="text-green-800 font-medium">
                                        <strong>Free 3-year extended warranty</strong> on electronics - in addition to your statutory rights.
                                    </p>
                                </div>

                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Guarantor</h3>
                                        <p className="text-gray-600 mb-3">
                                            <strong>Your Store S.r.l.</strong><br />
                                            Via Roma 123<br />
                                            00100 Roma, Italia
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Coverage</h3>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Manufacturing defects</li>
                                            <li>Battery capacity above 80% (for devices with batteries)</li>
                                            <li>Normal wear and tear exclusions apply</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Claim Process</h3>
                                        <ol className="list-decimal list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Submit online warranty claim form</li>
                                            <li>Receive prepaid return shipping label</li>
                                            <li>Send device for inspection</li>
                                            <li>Receive replacement or refurbished unit</li>
                                        </ol>
                                    </div>

                                    <div className="bg-yellow-50 border-l-4 border-yellow-400 p-4">
                                        <p className="text-yellow-800">
                                            <strong>Important:</strong> This commercial guarantee is in addition to, 
                                            not instead of, your statutory warranty rights under EU law.
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Liability & Force Majeure Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">Liability & Force Majeure</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Liability Limitations</h3>
                                        <p className="text-gray-600 mb-3">
                                            We accept unlimited liability for:
                                        </p>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Death or personal injury caused by our negligence</li>
                                            <li>Fraud or fraudulent misrepresentation</li>
                                            <li>Any matter for which it would be illegal to exclude liability</li>
                                        </ul>
                                        <p className="text-gray-600">
                                            For all other matters, our liability is limited to foreseeable damages 
                                            that were reasonably contemplated at the time of contract conclusion.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Force Majeure</h3>
                                        <p className="text-gray-600 mb-3">
                                            We are not liable for delays or failures caused by events beyond our 
                                            reasonable control, including:
                                        </p>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Natural disasters (floods, earthquakes, severe weather)</li>
                                            <li>Labor strikes or industrial action</li>
                                            <li>Cyber-attacks or system failures</li>
                                            <li>Government actions or regulatory changes</li>
                                            <li>Supplier failures or transportation disruptions</li>
                                        </ul>
                                    </div>
                                </div>
                            </section>

                            {/* Governing Law & Jurisdiction Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">Governing Law & Jurisdiction</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Applicable Law</h3>
                                        <p className="text-gray-600 mb-3">
                                            These terms are governed by <strong>Italian law</strong>. However, 
                                            mandatory consumer protection laws of your EU Member State remain unaffected 
                                            and will apply where they provide greater protection.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Dispute Resolution</h3>
                                        <p className="text-gray-600 mb-3">
                                            For consumer disputes, you may choose to use Alternative Dispute Resolution (ADR) 
                                            through the Italian Consumer Ombudsman or your national ADR body.
                                        </p>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <p className="text-gray-600">
                                                <strong>ADR Contact:</strong><br />
                                                Italian Consumer Ombudsman<br />
                                                Website: <a href="https://www.risolviconline.com" className="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">www.risolviconline.com</a>
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Court Jurisdiction</h3>
                                        <p className="text-gray-600 mb-3">
                                            Any legal proceedings may be brought in the courts of Rome, Italy, 
                                            or in the courts of your place of residence if you are a consumer.
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
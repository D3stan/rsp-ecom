import { Head } from '@inertiajs/react';
import Header from '@/components/header';

export default function ShippingReturns() {
    return (
        <>
            <Head title="Shipping & Returns - Your Store" />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">Shipping & Returns</h1>
                        <p className="text-lg text-gray-600">
                            Information about our shipping policies and your right of withdrawal
                        </p>
                    </div>

                    <div className="prose prose-lg max-w-none">
                        <div className="space-y-12">
                            {/* Shipping Policy Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">Shipping Policy</h2>
                                
                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Shipping Destinations</h3>
                                        <p className="text-gray-600 mb-3">
                                            We ship to all EU Member States. We do not ship to PO boxes or remote territories.
                                        </p>
                                        <p className="text-gray-600">
                                            For UK deliveries, please note that additional customs delays may apply post-Brexit.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Carriers & Methods</h3>
                                        <p className="text-gray-600 mb-3">
                                            We use trusted carriers including DHL, UPS, and local postal services. 
                                            We reserve the right to select the most appropriate carrier for your delivery.
                                        </p>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <h4 className="font-semibold text-black mb-2">Available Options:</h4>
                                            <ul className="list-disc list-inside text-gray-600 space-y-1">
                                                <li>Standard Delivery (3-5 business days)</li>
                                                <li>Express Delivery (1-2 business days)</li>
                                                <li>Tracked Delivery (with tracking number)</li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Shipping Costs</h3>
                                        <p className="text-gray-600 mb-3">
                                            All shipping costs include VAT and are displayed before checkout. 
                                            Free shipping is available for orders over €50.
                                        </p>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <table className="w-full text-sm">
                                                <thead>
                                                    <tr className="border-b border-gray-200">
                                                        <th className="text-left py-2 text-black">Destination</th>
                                                        <th className="text-left py-2 text-black">Standard</th>
                                                        <th className="text-left py-2 text-black">Express</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="text-gray-600">
                                                    <tr>
                                                        <td className="py-1">EU Countries</td>
                                                        <td className="py-1">€4.99</td>
                                                        <td className="py-1">€9.99</td>
                                                    </tr>
                                                    <tr>
                                                        <td className="py-1">UK</td>
                                                        <td className="py-1">€7.99</td>
                                                        <td className="py-1">€14.99</td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Dispatch & Delivery Times</h3>
                                        <p className="text-gray-600 mb-3">
                                            Orders are dispatched within 1-2 business days. Delivery takes 3-5 business days for standard shipping.
                                        </p>
                                        <p className="text-gray-600">
                                            Risk of loss or damage passes to you upon delivery to your specified address.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Missing or Damaged Parcels</h3>
                                        <p className="text-gray-600 mb-3">
                                            Please notify us within 48 hours of delivery if your parcel is missing or damaged. 
                                            Photo evidence may be required for damage claims.
                                        </p>
                                        <p className="text-gray-600">
                                            Contact us at: <a href="mailto:support@yourstore.com" className="text-blue-600 hover:underline">support@yourstore.com</a>
                                        </p>
                                    </div>
                                </div>
                            </section>

                            {/* Returns & Right of Withdrawal Section */}
                            <section>
                                <h2 className="text-3xl font-bold text-black mb-6">Returns & Right of Withdrawal</h2>
                                
                                <div className="bg-blue-50 border-l-4 border-blue-400 p-4 mb-6">
                                    <p className="text-blue-800 font-medium">
                                        <strong>Important:</strong> Our store does not offer free returns. Returns are only accepted 
                                        if the product is defective or we have made an error with your order.
                                    </p>
                                </div>

                                <div className="space-y-6">
                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">EU Right of Withdrawal</h3>
                                        <p className="text-gray-600 mb-3">
                                            Under EU consumer protection law, you have the right to withdraw from your purchase 
                                            within 14 calendar days of delivery, but only in the following cases:
                                        </p>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2 mb-4">
                                            <li>The product is defective or not as described</li>
                                            <li>We sent you the wrong item</li>
                                            <li>The product was damaged during shipping</li>
                                        </ul>
                                        <p className="text-gray-600">
                                            <strong>Note:</strong> We do not accept returns for change of mind, wrong size selection, 
                                            or other non-defect related reasons.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">How to Request a Return</h3>
                                        <p className="text-gray-600 mb-3">
                                            To request a return for a defective product or our error:
                                        </p>
                                        <ol className="list-decimal list-inside text-gray-600 space-y-2 mb-4">
                                            <li>Contact us within 14 days of delivery</li>
                                            <li>Provide your order number and description of the issue</li>
                                            <li>Include photos if the product is damaged</li>
                                            <li>We will provide a return authorization (RMA) number</li>
                                        </ol>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <p className="text-gray-600">
                                                <strong>Contact for Returns:</strong><br />
                                                Email: <a href="mailto:returns@yourstore.com" className="text-blue-600 hover:underline">returns@yourstore.com</a><br />
                                                Phone: +39 06 1234 5678
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Return Shipping</h3>
                                        <p className="text-gray-600 mb-3">
                                            For defective products or our errors, we will provide a prepaid return label. 
                                            For all other cases, return shipping costs are borne by the customer.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Condition of Returned Goods</h3>
                                        <p className="text-gray-600 mb-3">
                                            Products must be returned in their original condition and packaging. 
                                            Reasonable handling for inspection is allowed.
                                        </p>
                                        <p className="text-gray-600">
                                            We reserve the right to apply partial refunds for items that show excessive wear 
                                            beyond normal inspection handling.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Refund Processing</h3>
                                        <p className="text-gray-600 mb-3">
                                            Approved refunds will be processed within 14 days of receiving the returned item. 
                                            Refunds will be issued to the original payment method.
                                        </p>
                                        <p className="text-gray-600">
                                            We may withhold the refund until we receive and inspect the returned goods.
                                        </p>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Items Exempt from Returns</h3>
                                        <ul className="list-disc list-inside text-gray-600 space-y-2">
                                            <li>Personalized or custom-made products</li>
                                            <li>Sealed hygiene items once opened</li>
                                            <li>Perishable goods</li>
                                            <li>Digital content after download</li>
                                        </ul>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Return Address</h3>
                                        <div className="bg-gray-50 p-4 rounded-lg">
                                            <p className="text-gray-600">
                                                <strong>Returns Department</strong><br />
                                                Your Store S.r.l.<br />
                                                Via Roma 123<br />
                                                00100 Roma, Italy<br />
                                                <br />
                                                <strong>RMA Format:</strong> RMA-[ORDER-NUMBER]-[DATE]
                                            </p>
                                        </div>
                                    </div>

                                    <div>
                                        <h3 className="text-xl font-semibold text-black mb-3">Dispute Resolution</h3>
                                        <p className="text-gray-600 mb-3">
                                            For unresolved disputes, you may contact the Italian Consumer Ombudsman 
                                            or use the EU Alternative Dispute Resolution platform.
                                        </p>
                                        <p className="text-gray-600">
                                            ADR Contact: <a href="https://www.risolviconline.com" className="text-blue-600 hover:underline" target="_blank" rel="noopener noreferrer">www.risolviconline.com</a>
                                        </p>
                                    </div>
                                </div>
                            </section>
                        </div>
                    </div>

                    <div className="text-center mt-12 pt-8 border-t border-gray-200">
                        <p className="text-sm text-gray-500">
                            Last updated: August 5, 2025
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
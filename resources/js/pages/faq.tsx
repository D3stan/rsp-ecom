import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import useTranslation from '@/hooks/useTranslation';

interface FAQItem {
    id: string;
    question: string;
    answer: string;
    category: string;
}

const faqData: FAQItem[] = [
    // Ordering
    {
        id: 'order-1',
        question: 'How do I place an order?',
        answer: 'Simply browse our products, add items to your cart, and proceed to checkout. You can either create an account for faster future orders or checkout as a guest.',
        category: 'Ordering'
    },
    {
        id: 'order-2',
        question: 'Can I modify my order after placing it?',
        answer: 'Orders can be modified within 1 hour of placement. Please contact our customer service team immediately at support@rsp-industries.com or call +1 (555) 123-4567.',
        category: 'Ordering'
    },
    {
        id: 'order-3',
        question: 'Do I need an account to place an order?',
        answer: 'No, you can checkout as a guest. However, having an account makes future orders easier and allows you to track your order history.',
        category: 'Ordering'
    },
    {
        id: 'order-4',
        question: 'What payment methods do you accept?',
        answer: 'We accept all major credit cards (Visa, MasterCard, American Express), PayPal, and bank transfers. All payments are processed securely through Stripe.',
        category: 'Ordering'
    },

    // Shipping
    {
        id: 'shipping-1',
        question: 'How long does shipping take?',
        answer: 'Standard shipping takes 5-7 business days (€5.99), express shipping takes 2-3 business days (€9.99), and next day delivery is available (€19.99). Free standard shipping on orders over €50!',
        category: 'Shipping'
    },
    {
        id: 'shipping-2',
        question: 'Do you ship internationally?',
        answer: 'Yes, we ship to all EU countries. International shipping rates and delivery times vary by destination. Contact us for specific rates to your country.',
        category: 'Shipping'
    },
    {
        id: 'shipping-3',
        question: 'How can I track my order?',
        answer: 'Once your order ships, you\'ll receive a tracking number via email. You can also track your order by logging into your account and viewing your order history.',
        category: 'Shipping'
    },
    {
        id: 'shipping-4',
        question: 'What if my package is damaged or lost?',
        answer: 'If your package arrives damaged or is lost in transit, please contact us immediately. We\'ll work with the shipping carrier to resolve the issue and ensure you receive your items.',
        category: 'Shipping'
    },

    // Returns
    {
        id: 'returns-1',
        question: 'What is your return policy?',
        answer: 'We accept returns within 30 days of delivery. Items must be in original condition with original packaging and tags included. Items must be unworn and unused.',
        category: 'Returns'
    },
    {
        id: 'returns-2',
        question: 'Who pays for return shipping?',
        answer: 'We provide free return shipping labels for all returns. Simply contact our customer service team and we\'ll email you a prepaid return label.',
        category: 'Returns'
    },
    {
        id: 'returns-3',
        question: 'How long does it take to process a refund?',
        answer: 'Refunds are processed within 5-7 business days after we receive your return. You\'ll receive an email confirmation once the refund has been processed.',
        category: 'Returns'
    },
    {
        id: 'returns-4',
        question: 'Can I exchange an item instead of returning it?',
        answer: 'Yes! If you need a different size or color, we can process an exchange. Contact our customer service team to arrange an exchange.',
        category: 'Returns'
    },

    // Account
    {
        id: 'account-1',
        question: 'How do I create an account?',
        answer: 'Click the "Register" button in the top right corner of our website. You\'ll need to provide your name, email address, and create a password.',
        category: 'Account'
    },
    {
        id: 'account-2',
        question: 'I forgot my password. How do I reset it?',
        answer: 'Click the "Forgot Password" link on the login page. Enter your email address and we\'ll send you instructions to reset your password.',
        category: 'Account'
    },
    {
        id: 'account-3',
        question: 'How do I update my account information?',
        answer: 'Log into your account and go to "Account Settings" where you can update your personal information, addresses, and payment methods.',
        category: 'Account'
    },
    {
        id: 'account-4',
        question: 'Can I save multiple addresses?',
        answer: 'Yes! You can save multiple shipping and billing addresses in your account for faster checkout. Just go to "Account Settings" > "Addresses".',
        category: 'Account'
    },

    // General
    {
        id: 'general-1',
        question: 'How can I contact customer service?',
        answer: 'You can reach us by email at support@rsp-industries.com, call us at +1 (555) 123-4567, or use our contact form. Our hours are Monday-Friday, 9:00 AM - 5:00 PM EST.',
        category: 'General'
    },
    {
        id: 'general-2',
        question: 'Do you offer price matching?',
        answer: 'We strive to offer competitive prices. If you find a lower price on an identical item from a competitor, please contact us and we\'ll do our best to match it.',
        category: 'General'
    },
    {
        id: 'general-3',
        question: 'Is my personal information secure?',
        answer: 'Yes, we take your privacy seriously. We use SSL encryption, secure payment processing through Stripe, and never share your personal information with third parties without your consent.',
        category: 'General'
    },
    {
        id: 'general-4',
        question: 'Do you have a physical store?',
        answer: 'We are primarily an online retailer, but we do have a customer service center at 123 Business Street, City, State 12345. Please call ahead if you need to visit in person.',
        category: 'General'
    }
];

const categories = ['All', 'Ordering', 'Shipping', 'Returns', 'Account', 'General'];

export default function FAQ() {
    const { t } = useTranslation();
    const [openItems, setOpenItems] = useState<string[]>([]);
    const [selectedCategory, setSelectedCategory] = useState('All');

    const toggleItem = (id: string) => {
        setOpenItems(prev => 
            prev.includes(id) 
                ? prev.filter(item => item !== id)
                : [...prev, id]
        );
    };

    const filteredFAQ = selectedCategory === 'All' 
        ? faqData 
        : faqData.filter(item => item.category === selectedCategory);

    return (
        <>
            <Head title="FAQ - Frequently Asked Questions" />
            
            <div className="min-h-screen bg-white">
                <Header />

                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                    <div className="text-center mb-12">
                        <h1 className="text-4xl font-bold text-black mb-6">{t('faq_title')}</h1>
                        <p className="text-xl text-gray-600">
                            {t('faq_subtitle')}
                        </p>
                    </div>

                    {/* Category Filter */}
                    <div className="mb-8">
                        <div className="flex flex-wrap justify-center gap-2">
                            {categories.map((category) => (
                                <button
                                    key={category}
                                    onClick={() => setSelectedCategory(category)}
                                    className={`px-4 py-2 rounded-full border transition-colors ${
                                        selectedCategory === category
                                            ? 'bg-black text-white border-black'
                                            : 'bg-white text-gray-700 border-gray-300 hover:border-gray-400'
                                    }`}
                                >
                                    {t(`faq_categories.${category.toLowerCase()}`)}
                                </button>
                            ))}
                        </div>
                    </div>

                    {/* FAQ Items */}
                    <div className="space-y-4">
                        {filteredFAQ.map((item) => (
                            <div key={item.id} className="border border-gray-200 rounded-lg">
                                <button
                                    onClick={() => toggleItem(item.id)}
                                    className="w-full px-6 py-4 text-left flex justify-between items-center hover:bg-gray-50 transition-colors"
                                >
                                    <div>
                                        <span className="text-sm text-gray-500 uppercase tracking-wide">
                                            {item.category}
                                        </span>
                                        <h3 className="text-lg font-semibold text-black mt-1">
                                            {item.question}
                                        </h3>
                                    </div>
                                    {openItems.includes(item.id) ? (
                                        <ChevronUp className="h-5 w-5 text-gray-400 flex-shrink-0" />
                                    ) : (
                                        <ChevronDown className="h-5 w-5 text-gray-400 flex-shrink-0" />
                                    )}
                                </button>
                                
                                {openItems.includes(item.id) && (
                                    <div className="px-6 pb-4">
                                        <div className="text-gray-600 leading-relaxed">
                                            {item.answer}
                                        </div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Contact Section */}
                    <div className="mt-16 text-center bg-gray-50 rounded-lg p-8">
                        <h2 className="text-2xl font-bold text-black mb-4">{t('still_have_questions')}</h2>
                        <p className="text-gray-600 mb-6">
                            {t('faq_contact_description')}
                        </p>
                        <div className="flex flex-col sm:flex-row justify-center gap-4">
                            <a 
                                href="/contact" 
                                className="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-black hover:bg-gray-800 transition-colors"
                            >
                                {t('contact_us')}
                            </a>
                            <a 
                                href="mailto:support@rsp-industries.com" 
                                className="inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors"
                            >
                                {t('email_support')}
                            </a>
                        </div>
                        <div className="mt-4 text-sm text-gray-500">
                            {t('response_time')}
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

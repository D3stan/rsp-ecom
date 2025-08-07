import { Head } from '@inertiajs/react';
import Header from '@/components/header';
import { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import useTranslation from '@/hooks/useTranslation';

interface FAQItem {
    id: string;
    questionKey: string;
    answerKey: string;
    category: string;
}

const categories = ['all', 'ordering', 'shipping', 'returns', 'account', 'general'];

export default function FAQ() {
    const { t } = useTranslation();
    const [openItems, setOpenItems] = useState<string[]>([]);
    const [selectedCategory, setSelectedCategory] = useState('all');

    const faqData: FAQItem[] = [
        // Ordering
        {
            id: 'order-1',
            questionKey: 'faq_items.ordering.question_1',
            answerKey: 'faq_items.ordering.answer_1',
            category: 'ordering'
        },
        {
            id: 'order-2',
            questionKey: 'faq_items.ordering.question_2',
            answerKey: 'faq_items.ordering.answer_2',
            category: 'ordering'
        },
        {
            id: 'order-3',
            questionKey: 'faq_items.ordering.question_3',
            answerKey: 'faq_items.ordering.answer_3',
            category: 'ordering'
        },
        {
            id: 'order-4',
            questionKey: 'faq_items.ordering.question_4',
            answerKey: 'faq_items.ordering.answer_4',
            category: 'ordering'
        },

        // Shipping
        {
            id: 'shipping-1',
            questionKey: 'faq_items.shipping.question_1',
            answerKey: 'faq_items.shipping.answer_1',
            category: 'shipping'
        },
        {
            id: 'shipping-2',
            questionKey: 'faq_items.shipping.question_2',
            answerKey: 'faq_items.shipping.answer_2',
            category: 'shipping'
        },
        {
            id: 'shipping-3',
            questionKey: 'faq_items.shipping.question_3',
            answerKey: 'faq_items.shipping.answer_3',
            category: 'shipping'
        },
        {
            id: 'shipping-4',
            questionKey: 'faq_items.shipping.question_4',
            answerKey: 'faq_items.shipping.answer_4',
            category: 'shipping'
        },

        // Returns
        {
            id: 'returns-1',
            questionKey: 'faq_items.returns.question_1',
            answerKey: 'faq_items.returns.answer_1',
            category: 'returns'
        },
        {
            id: 'returns-2',
            questionKey: 'faq_items.returns.question_2',
            answerKey: 'faq_items.returns.answer_2',
            category: 'returns'
        },
        {
            id: 'returns-3',
            questionKey: 'faq_items.returns.question_3',
            answerKey: 'faq_items.returns.answer_3',
            category: 'returns'
        },
        {
            id: 'returns-4',
            questionKey: 'faq_items.returns.question_4',
            answerKey: 'faq_items.returns.answer_4',
            category: 'returns'
        },

        // Account
        {
            id: 'account-1',
            questionKey: 'faq_items.account.question_1',
            answerKey: 'faq_items.account.answer_1',
            category: 'account'
        },
        {
            id: 'account-2',
            questionKey: 'faq_items.account.question_2',
            answerKey: 'faq_items.account.answer_2',
            category: 'account'
        },
        {
            id: 'account-3',
            questionKey: 'faq_items.account.question_3',
            answerKey: 'faq_items.account.answer_3',
            category: 'account'
        },
        {
            id: 'account-4',
            questionKey: 'faq_items.account.question_4',
            answerKey: 'faq_items.account.answer_4',
            category: 'account'
        },

        // General
        {
            id: 'general-1',
            questionKey: 'faq_items.general.question_1',
            answerKey: 'faq_items.general.answer_1',
            category: 'general'
        },
        {
            id: 'general-2',
            questionKey: 'faq_items.general.question_2',
            answerKey: 'faq_items.general.answer_2',
            category: 'general'
        },
        {
            id: 'general-3',
            questionKey: 'faq_items.general.question_3',
            answerKey: 'faq_items.general.answer_3',
            category: 'general'
        },
        {
            id: 'general-4',
            questionKey: 'faq_items.general.question_4',
            answerKey: 'faq_items.general.answer_4',
            category: 'general'
        }
    ];

    const toggleItem = (id: string) => {
        setOpenItems(prev => 
            prev.includes(id) 
                ? prev.filter(item => item !== id)
                : [...prev, id]
        );
    };

    const filteredFAQ = selectedCategory === 'all' 
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
                                    {t(`faq_categories.${category}`)}
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
                                            {t(`faq_categories.${item.category}`)}
                                        </span>
                                        <h3 className="text-lg font-semibold text-black mt-1">
                                            {t(item.questionKey)}
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
                                            {t(item.answerKey)}
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

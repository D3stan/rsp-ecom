import Header from '@/components/header';
import useTranslation from '@/hooks/useTranslation';
import { Head } from '@inertiajs/react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { useState } from 'react';

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
            category: 'ordering',
        },
        {
            id: 'order-2',
            questionKey: 'faq_items.ordering.question_2',
            answerKey: 'faq_items.ordering.answer_2',
            category: 'ordering',
        },
        {
            id: 'order-3',
            questionKey: 'faq_items.ordering.question_3',
            answerKey: 'faq_items.ordering.answer_3',
            category: 'ordering',
        },
        {
            id: 'order-4',
            questionKey: 'faq_items.ordering.question_4',
            answerKey: 'faq_items.ordering.answer_4',
            category: 'ordering',
        },

        // Shipping
        {
            id: 'shipping-1',
            questionKey: 'faq_items.shipping.question_1',
            answerKey: 'faq_items.shipping.answer_1',
            category: 'shipping',
        },
        {
            id: 'shipping-2',
            questionKey: 'faq_items.shipping.question_2',
            answerKey: 'faq_items.shipping.answer_2',
            category: 'shipping',
        },
        {
            id: 'shipping-3',
            questionKey: 'faq_items.shipping.question_3',
            answerKey: 'faq_items.shipping.answer_3',
            category: 'shipping',
        },
        {
            id: 'shipping-4',
            questionKey: 'faq_items.shipping.question_4',
            answerKey: 'faq_items.shipping.answer_4',
            category: 'shipping',
        },

        // Returns
        {
            id: 'returns-1',
            questionKey: 'faq_items.returns.question_1',
            answerKey: 'faq_items.returns.answer_1',
            category: 'returns',
        },
        {
            id: 'returns-2',
            questionKey: 'faq_items.returns.question_2',
            answerKey: 'faq_items.returns.answer_2',
            category: 'returns',
        },
        {
            id: 'returns-3',
            questionKey: 'faq_items.returns.question_3',
            answerKey: 'faq_items.returns.answer_3',
            category: 'returns',
        },
        {
            id: 'returns-4',
            questionKey: 'faq_items.returns.question_4',
            answerKey: 'faq_items.returns.answer_4',
            category: 'returns',
        },

        // Account
        {
            id: 'account-1',
            questionKey: 'faq_items.account.question_1',
            answerKey: 'faq_items.account.answer_1',
            category: 'account',
        },
        {
            id: 'account-2',
            questionKey: 'faq_items.account.question_2',
            answerKey: 'faq_items.account.answer_2',
            category: 'account',
        },
        {
            id: 'account-3',
            questionKey: 'faq_items.account.question_3',
            answerKey: 'faq_items.account.answer_3',
            category: 'account',
        },
        {
            id: 'account-4',
            questionKey: 'faq_items.account.question_4',
            answerKey: 'faq_items.account.answer_4',
            category: 'account',
        },

        // General
        {
            id: 'general-1',
            questionKey: 'faq_items.general.question_1',
            answerKey: 'faq_items.general.answer_1',
            category: 'general',
        },
        {
            id: 'general-2',
            questionKey: 'faq_items.general.question_2',
            answerKey: 'faq_items.general.answer_2',
            category: 'general',
        },
        {
            id: 'general-3',
            questionKey: 'faq_items.general.question_3',
            answerKey: 'faq_items.general.answer_3',
            category: 'general',
        },
        {
            id: 'general-4',
            questionKey: 'faq_items.general.question_4',
            answerKey: 'faq_items.general.answer_4',
            category: 'general',
        },
    ];

    const toggleItem = (id: string) => {
        setOpenItems((prev) => (prev.includes(id) ? prev.filter((item) => item !== id) : [...prev, id]));
    };

    const filteredFAQ = selectedCategory === 'all' ? faqData : faqData.filter((item) => item.category === selectedCategory);

    return (
        <>
            <Head title="FAQ - Frequently Asked Questions" />

            <div className="min-h-screen bg-white">
                <Header />

                <div className="mx-auto max-w-4xl px-4 py-16 sm:px-6 lg:px-8">
                    <div className="mb-12 text-center">
                        <h1 className="mb-6 text-4xl font-bold text-black">{t('faq_title')}</h1>
                        <p className="text-xl text-gray-600">{t('faq_subtitle')}</p>
                    </div>

                    {/* Category Filter */}
                    <div className="mb-8">
                        <div className="flex flex-wrap justify-center gap-2">
                            {categories.map((category) => (
                                <button
                                    key={category}
                                    onClick={() => setSelectedCategory(category)}
                                    className={`rounded-full border px-4 py-2 transition-colors ${
                                        selectedCategory === category
                                            ? 'border-black bg-black text-white'
                                            : 'border-gray-300 bg-white text-gray-700 hover:border-gray-400'
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
                            <div key={item.id} className="rounded-lg border border-gray-200">
                                <button
                                    onClick={() => toggleItem(item.id)}
                                    className="flex w-full items-center justify-between px-6 py-4 text-left transition-colors hover:bg-gray-50"
                                >
                                    <div>
                                        <span className="text-sm tracking-wide text-gray-500 uppercase">{t(`faq_categories.${item.category}`)}</span>
                                        <h3 className="mt-1 text-lg font-semibold text-black">{t(item.questionKey)}</h3>
                                    </div>
                                    {openItems.includes(item.id) ? (
                                        <ChevronUp className="h-5 w-5 flex-shrink-0 text-gray-400" />
                                    ) : (
                                        <ChevronDown className="h-5 w-5 flex-shrink-0 text-gray-400" />
                                    )}
                                </button>

                                {openItems.includes(item.id) && (
                                    <div className="px-6 pb-4">
                                        <div className="leading-relaxed text-gray-600">{t(item.answerKey)}</div>
                                    </div>
                                )}
                            </div>
                        ))}
                    </div>

                    {/* Contact Section */}
                    <div className="mt-16 rounded-lg bg-gray-50 p-8 text-center">
                        <h2 className="mb-4 text-2xl font-bold text-black">{t('still_have_questions')}</h2>
                        <p className="mb-6 text-gray-600">{t('faq_contact_description')}</p>
                        <div className="flex flex-col justify-center gap-4 sm:flex-row">
                            <a
                                href="/contact"
                                className="inline-flex items-center justify-center rounded-md border border-transparent bg-black px-6 py-3 text-base font-medium text-white transition-colors hover:bg-gray-800"
                            >
                                {t('contact_us')}
                            </a>
                            <a
                                href="mailto:support@rsp-industries.com"
                                className="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-6 py-3 text-base font-medium text-gray-700 transition-colors hover:bg-gray-50"
                            >
                                {t('email_support')}
                            </a>
                        </div>
                        <div className="mt-4 text-sm text-gray-500">{t('response_time')}</div>
                    </div>
                </div>
            </div>
        </>
    );
}

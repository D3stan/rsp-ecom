import PublicLayout from '@/layouts/public-layout';
import { Head, useForm, usePage } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { type SharedData } from '@/types';
import { Clock, Mail, Phone } from 'lucide-react';

interface ContactPageProps extends SharedData {
    errors: Record<string, string>;
}

export default function Contact() {
    // The outcome of a submission is the controller flash, toasted by the FlashToaster (app.tsx)
    const { errors } = usePage<ContactPageProps>().props;
    const { t } = useTranslation();
    const supportEmail = useSetting('contact.support_email', '');
    const phone = useSetting('contact.phone', '');
    const businessHours = useSetting('contact.business_hours', '');

    const { data, setData, post, processing, reset } = useForm({
        firstName: '',
        lastName: '',
        email: '',
        subject: '',
        message: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('contact.store'), {
            onSuccess: () => {
                reset();
            },
        });
    };

    return (
        <PublicLayout currentPage="contact">
            <Head title="Contact Us - Your Store" />

            {/* Main Content */}
            <div className="mx-auto max-w-7xl px-4 py-16 sm:px-6 lg:px-8">
                <div className="mb-16 text-center">
                    <h1 className="mb-6 text-4xl font-bold text-black">{t('contact.title')}</h1>
                    <p className="mx-auto max-w-3xl text-xl text-gray-600">{t('contact.subtitle')}</p>
                </div>

                <div className="grid gap-12 lg:grid-cols-2">
                    {/* Contact Form */}
                    <div>
                        <Card className="border border-gray-200 pb-0 shadow-sm">
                            <CardHeader className="bg-black text-white">
                                <CardTitle>{t('contact.send_message')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6 bg-white p-6">
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="firstName" className="text-gray-900">
                                                {t('contact.first_name')} {t('contact.required_field')}
                                            </Label>
                                            <Input
                                                id="firstName"
                                                value={data.firstName}
                                                onChange={(e) => setData('firstName', e.target.value)}
                                                placeholder="John"
                                                className={`border-gray-300 focus:border-black focus:ring-black ${errors.firstName ? 'border-red-500' : ''}`}
                                                required
                                            />
                                            {errors.firstName && <p className="mt-1 text-sm text-red-500">{errors.firstName}</p>}
                                        </div>
                                        <div>
                                            <Label htmlFor="lastName" className="text-gray-900">
                                                {t('contact.last_name')} {t('contact.required_field')}
                                            </Label>
                                            <Input
                                                id="lastName"
                                                value={data.lastName}
                                                onChange={(e) => setData('lastName', e.target.value)}
                                                placeholder="Doe"
                                                className={`border-gray-300 focus:border-black focus:ring-black ${errors.lastName ? 'border-red-500' : ''}`}
                                                required
                                            />
                                            {errors.lastName && <p className="mt-1 text-sm text-red-500">{errors.lastName}</p>}
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="email" className="text-gray-900">
                                            {t('contact.email')} {t('contact.required_field')}
                                        </Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="john@example.com"
                                            className={`border-gray-300 focus:border-black focus:ring-black ${errors.email ? 'border-red-500' : ''}`}
                                            required
                                        />
                                        {errors.email && <p className="mt-1 text-sm text-red-500">{errors.email}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="subject" className="text-gray-900">
                                            {t('contact.subject')} {t('contact.required_field')}
                                        </Label>
                                        <Input
                                            id="subject"
                                            value={data.subject}
                                            onChange={(e) => setData('subject', e.target.value)}
                                            placeholder="How can we help you?"
                                            className={`border-gray-300 focus:border-black focus:ring-black ${errors.subject ? 'border-red-500' : ''}`}
                                            required
                                        />
                                        {errors.subject && <p className="mt-1 text-sm text-red-500">{errors.subject}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="message" className="text-gray-900">
                                            {t('contact.message')} {t('contact.required_field')}
                                        </Label>
                                        <textarea
                                            id="message"
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            className={`min-h-[120px] w-full resize-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:border-black focus:ring-2 focus:ring-black focus:outline-none ${errors.message ? 'border-red-500' : ''}`}
                                            placeholder="Tell us more about your inquiry..."
                                            required
                                        />
                                        {errors.message && <p className="mt-1 text-sm text-red-500">{errors.message}</p>}
                                    </div>
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full bg-black text-white hover:bg-gray-900 disabled:opacity-50"
                                    >
                                        {processing ? t('contact.sending') : t('contact.send_message_btn')}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Contact Information */}
                    <div className="space-y-8">
                        <Card className="border border-gray-200 bg-white shadow-sm">
                            <CardContent className="p-6">
                                <div className="flex items-start space-x-4">
                                    <div className="rounded-full bg-black p-3 text-white">
                                        <Mail className="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 className="mb-1 font-semibold text-black">{t('contact.email_us')}</h3>
                                        <p className="text-gray-600">
                                            <a href={`mailto:${supportEmail}`} className="hover:underline">
                                                {supportEmail}
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        {phone.trim() !== '' && (
                            <Card className="border border-gray-200 bg-white shadow-sm">
                                <CardContent className="p-6">
                                    <div className="flex items-start space-x-4">
                                        <div className="rounded-full bg-black p-3 text-white">
                                            <Phone className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h3 className="mb-1 font-semibold text-black">{t('contact.call_us')}</h3>
                                            <p className="text-gray-600">
                                                <a href={`tel:${phone.replace(/[^+\d]/g, '')}`} className="hover:underline">
                                                    {phone}
                                                </a>
                                            </p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {businessHours.trim() !== '' && (
                            <Card className="border border-gray-200 bg-white shadow-sm">
                                <CardContent className="p-6">
                                    <div className="flex items-start space-x-4">
                                        <div className="rounded-full bg-black p-3 text-white">
                                            <Clock className="h-5 w-5" />
                                        </div>
                                        <div>
                                            <h3 className="mb-1 font-semibold text-black">{t('contact.business_hours')}</h3>
                                            <p className="text-gray-600">{businessHours}</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

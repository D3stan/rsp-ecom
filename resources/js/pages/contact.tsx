import { Head, useForm, usePage } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Alert, AlertDescription } from '@/components/ui/alert';
import {
    Mail,
    Phone,
    Clock,
    CheckCircle,
    AlertCircle,
} from 'lucide-react';
import { type SharedData } from '@/types';

interface ContactPageProps extends SharedData {
    errors: Record<string, string>;
    success?: string;
    error?: string;
}

export default function Contact() {
    const { errors, success, error } = usePage<ContactPageProps>().props;
    
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
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div className="text-center mb-16">
                    <h1 className="text-4xl font-bold text-black mb-6">Contact Us</h1>
                    <p className="text-xl text-gray-600 max-w-3xl mx-auto">
                        Have a question or need assistance? We're here to help! 
                        Reach out to us through any of the methods below.
                    </p>
                </div>

                {/* Success/Error Messages */}
                {success && (
                    <div className="mb-8 max-w-2xl mx-auto">
                        <Alert className="border-green-200 bg-green-50">
                            <CheckCircle className="h-4 w-4 text-green-600" />
                            <AlertDescription className="text-green-800">
                                {success}
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                {error && (
                    <div className="mb-8 max-w-2xl mx-auto">
                        <Alert className="border-red-200 bg-red-50">
                            <AlertCircle className="h-4 w-4 text-red-600" />
                            <AlertDescription className="text-red-800">
                                {error}
                            </AlertDescription>
                        </Alert>
                    </div>
                )}

                <div className="grid lg:grid-cols-2 gap-12">
                    {/* Contact Form */}
                    <div>
                        <Card className="border border-gray-200 shadow-sm pb-0">
                            <CardHeader className="bg-black text-white">
                                <CardTitle>Send us a Message</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6 p-6 bg-white">
                                <form onSubmit={handleSubmit} className="space-y-6">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="firstName" className="text-gray-900">First Name *</Label>
                                            <Input 
                                                id="firstName" 
                                                value={data.firstName}
                                                onChange={(e) => setData('firstName', e.target.value)}
                                                placeholder="John" 
                                                className={`border-gray-300 focus:border-black focus:ring-black ${errors.firstName ? 'border-red-500' : ''}`}
                                                required
                                            />
                                            {errors.firstName && <p className="text-red-500 text-sm mt-1">{errors.firstName}</p>}
                                        </div>
                                        <div>
                                            <Label htmlFor="lastName" className="text-gray-900">Last Name *</Label>
                                            <Input 
                                                id="lastName" 
                                                value={data.lastName}
                                                onChange={(e) => setData('lastName', e.target.value)}
                                                placeholder="Doe" 
                                                className={`border-gray-300 focus:border-black focus:ring-black ${errors.lastName ? 'border-red-500' : ''}`}
                                                required
                                            />
                                            {errors.lastName && <p className="text-red-500 text-sm mt-1">{errors.lastName}</p>}
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="email" className="text-gray-900">Email *</Label>
                                        <Input 
                                            id="email" 
                                            type="email" 
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                            placeholder="john@example.com" 
                                            className={`border-gray-300 focus:border-black focus:ring-black ${errors.email ? 'border-red-500' : ''}`}
                                            required
                                        />
                                        {errors.email && <p className="text-red-500 text-sm mt-1">{errors.email}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="subject" className="text-gray-900">Subject *</Label>
                                        <Input 
                                            id="subject" 
                                            value={data.subject}
                                            onChange={(e) => setData('subject', e.target.value)}
                                            placeholder="How can we help you?" 
                                            className={`border-gray-300 focus:border-black focus:ring-black ${errors.subject ? 'border-red-500' : ''}`}
                                            required
                                        />
                                        {errors.subject && <p className="text-red-500 text-sm mt-1">{errors.subject}</p>}
                                    </div>
                                    <div>
                                        <Label htmlFor="message" className="text-gray-900">Message *</Label>
                                        <textarea 
                                            id="message"
                                            value={data.message}
                                            onChange={(e) => setData('message', e.target.value)}
                                            className={`w-full min-h-[120px] px-3 py-2 border border-gray-300 rounded-md resize-none focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-gray-900 ${errors.message ? 'border-red-500' : ''}`}
                                            placeholder="Tell us more about your inquiry..."
                                            required
                                        />
                                        {errors.message && <p className="text-red-500 text-sm mt-1">{errors.message}</p>}
                                    </div>
                                    <Button 
                                        type="submit"
                                        disabled={processing}
                                        className="w-full bg-black text-white hover:bg-gray-900 disabled:opacity-50"
                                    >
                                        {processing ? 'Sending...' : 'Send Message'}
                                    </Button>
                                </form>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Contact Information */}
                    <div className="space-y-8">
                        <Card className="border border-gray-200 shadow-sm bg-white">
                            <CardContent className="p-6">
                                <div className="flex items-start space-x-4">
                                    <div className="bg-black text-white p-3 rounded-full">
                                        <Mail className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-black mb-1">Email Us</h3>
                                        <p className="text-gray-600">support@store.com</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border border-gray-200 shadow-sm bg-white">
                            <CardContent className="p-6">
                                <div className="flex items-start space-x-4">
                                    <div className="bg-black text-white p-3 rounded-full">
                                        <Phone className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-black mb-1">Call Us</h3>
                                        <p className="text-gray-600">+1 (555) 123-4567</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card className="border border-gray-200 shadow-sm bg-white">
                            <CardContent className="p-6">
                                <div className="flex items-start space-x-4">
                                    <div className="bg-black text-white p-3 rounded-full">
                                        <Clock className="w-5 h-5" />
                                    </div>
                                    <div>
                                        <h3 className="font-semibold text-black mb-1">Business Hours</h3>
                                        <p className="text-gray-600">Monday - Friday: 9:00 AM - 6:00 PM</p>
                                        <p className="text-gray-600">Saturday: 10:00 AM - 4:00 PM</p>
                                        <p className="text-gray-600">Sunday: Closed</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

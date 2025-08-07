import { Head } from '@inertiajs/react';
import PublicLayout from '@/layouts/public-layout';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Mail,
    Phone,
    MapPin,
    Clock
} from 'lucide-react';

export default function Contact() {
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

                <div className="grid lg:grid-cols-2 gap-12">
                    {/* Contact Form */}
                    <div>
                        <Card className="border border-gray-200 shadow-sm pb-0">
                            <CardHeader className="bg-black text-white">
                                <CardTitle>Send us a Message</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-6 p-6 bg-white">
                                <div className="grid grid-cols-2 gap-4">
                                    <div>
                                        <Label htmlFor="firstName" className="text-gray-900">First Name</Label>
                                        <Input id="firstName" placeholder="John" className="border-gray-300 focus:border-black focus:ring-black" />
                                    </div>
                                    <div>
                                        <Label htmlFor="lastName" className="text-gray-900">Last Name</Label>
                                        <Input id="lastName" placeholder="Doe" className="border-gray-300 focus:border-black focus:ring-black" />
                                    </div>
                                </div>
                                <div>
                                    <Label htmlFor="email" className="text-gray-900">Email</Label>
                                    <Input id="email" type="email" placeholder="john@example.com" className="border-gray-300 focus:border-black focus:ring-black" />
                                </div>
                                <div>
                                    <Label htmlFor="subject" className="text-gray-900">Subject</Label>
                                    <Input id="subject" placeholder="How can we help you?" className="border-gray-300 focus:border-black focus:ring-black" />
                                </div>
                                <div>
                                    <Label htmlFor="message" className="text-gray-900">Message</Label>
                                    <textarea 
                                        id="message"
                                        className="w-full min-h-[120px] px-3 py-2 border border-gray-300 rounded-md resize-none focus:outline-none focus:ring-2 focus:ring-black focus:border-black text-gray-900"
                                        placeholder="Tell us more about your inquiry..."
                                    />
                                </div>
                                <Button className="w-full bg-black text-white hover:bg-gray-900">Send Message</Button>
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

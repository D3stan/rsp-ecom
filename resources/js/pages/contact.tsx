import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { 
    Search, 
    ShoppingCart, 
    User, 
    Heart,
    Mail,
    Phone,
    MapPin,
    Clock
} from 'lucide-react';

export default function Contact() {
    const { auth } = usePage<SharedData>().props;

    return (
        <>
            <Head title="Contact Us - Your Store" />
            
            <div className="min-h-screen bg-white">
                {/* Header */}
                <header className="border-b border-gray-200">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center justify-between h-16">
                            {/* Logo */}
                            <div className="flex-shrink-0">
                                <Link href="/" className="text-2xl font-bold text-black">
                                    Store
                                </Link>
                            </div>

                            {/* Search Bar */}
                            <div className="flex-1 max-w-2xl mx-8">
                                <div className="relative">
                                    <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400 w-4 h-4" />
                                    <Input 
                                        type="search"
                                        placeholder="Search products..."
                                        className="pl-10 pr-4"
                                    />
                                </div>
                            </div>

                            {/* Navigation & Actions */}
                            <div className="flex items-center space-x-4">
                                <nav className="hidden md:flex space-x-6">
                                    <Link href="/products" className="text-gray-600 hover:text-black">
                                        Categories
                                    </Link>
                                    <Link href="/about" className="text-gray-600 hover:text-black">
                                        About
                                    </Link>
                                    <Link href="/contact" className="text-black font-medium">
                                        Contact
                                    </Link>
                                </nav>

                                <div className="flex items-center space-x-3">
                                    {auth.user ? (
                                        <>
                                            <Button variant="ghost" size="icon">
                                                <Heart className="w-5 h-5" />
                                            </Button>
                                            <Button variant="ghost" size="icon" className="relative">
                                                <ShoppingCart className="w-5 h-5" />
                                                <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                                    3
                                                </Badge>
                                            </Button>
                                            <Link href={route('dashboard')}>
                                                <Button variant="ghost" size="icon">
                                                    <User className="w-5 h-5" />
                                                </Button>
                                            </Link>
                                        </>
                                    ) : (
                                        <>
                                            <Button variant="ghost" size="icon" className="relative">
                                                <ShoppingCart className="w-5 h-5" />
                                                <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                                    0
                                                </Badge>
                                            </Button>
                                            <Link href={route('login')}>
                                                <Button variant="outline" size="sm">
                                                    Login
                                                </Button>
                                            </Link>
                                            <Link href={route('register')}>
                                                <Button size="sm">
                                                    Register
                                                </Button>
                                            </Link>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </header>

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
                            <Card>
                                <CardHeader>
                                    <CardTitle>Send us a Message</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="firstName">First Name</Label>
                                            <Input id="firstName" placeholder="John" />
                                        </div>
                                        <div>
                                            <Label htmlFor="lastName">Last Name</Label>
                                            <Input id="lastName" placeholder="Doe" />
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="email">Email</Label>
                                        <Input id="email" type="email" placeholder="john@example.com" />
                                    </div>
                                    <div>
                                        <Label htmlFor="subject">Subject</Label>
                                        <Input id="subject" placeholder="How can we help you?" />
                                    </div>
                                    <div>
                                        <Label htmlFor="message">Message</Label>
                                        <textarea 
                                            id="message"
                                            className="w-full min-h-[120px] px-3 py-2 border border-gray-300 rounded-md resize-none focus:outline-none focus:ring-2 focus:ring-black focus:border-transparent"
                                            placeholder="Tell us more about your inquiry..."
                                        />
                                    </div>
                                    <Button className="w-full">Send Message</Button>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Contact Information */}
                        <div className="space-y-8">
                            <Card>
                                <CardContent className="p-6">
                                    <div className="flex items-start space-x-4">
                                        <div className="bg-black text-white p-3 rounded-full">
                                            <Mail className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-black mb-1">Email Us</h3>
                                            <p className="text-gray-600">support@store.com</p>
                                            <p className="text-gray-600">sales@store.com</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="p-6">
                                    <div className="flex items-start space-x-4">
                                        <div className="bg-black text-white p-3 rounded-full">
                                            <Phone className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-black mb-1">Call Us</h3>
                                            <p className="text-gray-600">+1 (555) 123-4567</p>
                                            <p className="text-gray-600">+1 (555) 765-4321</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
                                <CardContent className="p-6">
                                    <div className="flex items-start space-x-4">
                                        <div className="bg-black text-white p-3 rounded-full">
                                            <MapPin className="w-5 h-5" />
                                        </div>
                                        <div>
                                            <h3 className="font-semibold text-black mb-1">Visit Us</h3>
                                            <p className="text-gray-600">123 Tech Street</p>
                                            <p className="text-gray-600">Innovation City, IC 12345</p>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            <Card>
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
            </div>
        </>
    );
}

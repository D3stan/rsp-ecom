import { type SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import Header from '@/components/header';
import { 
    Star, 
    ArrowRight,
    Smartphone,
    Laptop,
    Headphones,
    Watch,
    Camera,
    Gamepad2
} from 'lucide-react';

interface Product {
    id: number;
    name: string;
    price: number;
    originalPrice?: number;
    rating: number;
    reviews: number;
    image: string;
    badge?: string;
}

interface Category {
    id: number;
    name: string;
    slug: string;
    icon: any;
    count: number;
}

export default function Home() {

    // Mock data - in real app this would come from props
    const featuredProducts: Product[] = [
        {
            id: 1,
            name: "Wireless Bluetooth Headphones",
            price: 79.99,
            originalPrice: 99.99,
            rating: 4.5,
            reviews: 124,
            image: "/api/placeholder/300/300",
            badge: "Best Seller"
        },
        {
            id: 2,
            name: "Smart Fitness Watch",
            price: 199.99,
            rating: 4.8,
            reviews: 89,
            image: "/api/placeholder/300/300",
            badge: "New"
        },
        {
            id: 3,
            name: "Portable Laptop Stand",
            price: 29.99,
            originalPrice: 39.99,
            rating: 4.3,
            reviews: 67,
            image: "/api/placeholder/300/300"
        },
        {
            id: 4,
            name: "Wireless Phone Charger",
            price: 24.99,
            rating: 4.6,
            reviews: 203,
            image: "/api/placeholder/300/300"
        },
        {
            id: 5,
            name: "USB-C Hub",
            price: 49.99,
            rating: 4.4,
            reviews: 156,
            image: "/api/placeholder/300/300"
        },
        {
            id: 6,
            name: "Mechanical Keyboard",
            price: 129.99,
            originalPrice: 149.99,
            rating: 4.7,
            reviews: 91,
            image: "/api/placeholder/300/300",
            badge: "Sale"
        }
    ];

    const categories: Category[] = [
        { id: 1, name: "Smartphones", slug: "smartphones", icon: Smartphone, count: 45 },
        { id: 2, name: "Laptops", slug: "laptops", icon: Laptop, count: 32 },
        { id: 3, name: "Audio", slug: "audio", icon: Headphones, count: 78 },
        { id: 4, name: "Wearables", slug: "wearables", icon: Watch, count: 23 },
        { id: 5, name: "Cameras", slug: "cameras", icon: Camera, count: 19 },
        { id: 6, name: "Gaming", slug: "gaming", icon: Gamepad2, count: 56 }
    ];

    const renderStars = (rating: number) => {
        return Array.from({ length: 5 }, (_, i) => (
            <Star 
                key={i} 
                className={`w-4 h-4 ${i < Math.floor(rating) ? 'fill-yellow-400 text-yellow-400' : 'text-gray-300'}`} 
            />
        ));
    };

    return (
        <>
            <Head title="Home - Your Store" />
            
            <div className="min-h-screen bg-white">
                {/* Header */}
                <Header currentPage="home" />

                {/* Hero Section */}
                <section className="bg-gray-50 py-20">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center">
                            <h1 className="text-4xl md:text-6xl font-bold text-black mb-6">
                                Discover Amazing
                                <span className="block">Products</span>
                            </h1>
                            <p className="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
                                Find the perfect tech products for your lifestyle. Quality, innovation, and style in every purchase.
                            </p>
                            <div className="flex flex-col sm:flex-row gap-4 justify-center">
                                <Button size="lg" className="px-8">
                                    Shop Now
                                    <ArrowRight className="w-4 h-4 ml-2" />
                                </Button>
                                <Button variant="outline" size="lg" className="px-8">
                                    View Deals
                                </Button>
                            </div>
                        </div>
                    </div>
                </section>

                {/* Featured Products */}
                <section className="py-16">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-black mb-4">Featured Products</h2>
                            <p className="text-gray-600 max-w-2xl mx-auto">
                                Discover our most popular and highly-rated products
                            </p>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            {featuredProducts.map((product) => (
                                <Card key={product.id} className="group cursor-pointer hover:shadow-lg transition-shadow">
                                    <div className="relative overflow-hidden rounded-t-xl">
                                        <img 
                                            src={product.image} 
                                            alt={product.name}
                                            className="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-200"
                                        />
                                        {product.badge && (
                                            <Badge className="absolute top-2 left-2">
                                                {product.badge}
                                            </Badge>
                                        )}
                                        <Button 
                                            size="sm" 
                                            className="absolute bottom-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity"
                                        >
                                            Add to Cart
                                        </Button>
                                    </div>
                                    <CardContent className="p-4">
                                        <h3 className="font-semibold text-black mb-2 line-clamp-2">
                                            {product.name}
                                        </h3>
                                        <div className="flex items-center gap-1 mb-2">
                                            {renderStars(product.rating)}
                                            <span className="text-sm text-gray-500 ml-1">
                                                ({product.reviews})
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className="text-lg font-bold text-black">
                                                ${product.price}
                                            </span>
                                            {product.originalPrice && (
                                                <span className="text-sm text-gray-500 line-through">
                                                    ${product.originalPrice}
                                                </span>
                                            )}
                                        </div>
                                    </CardContent>
                                </Card>
                            ))}
                        </div>

                        <div className="text-center mt-12">
                            <Button variant="outline" size="lg">
                                View All Products
                                <ArrowRight className="w-4 h-4 ml-2" />
                            </Button>
                        </div>
                    </div>
                </section>

                {/* Categories Section */}
                <section className="py-16 bg-gray-50">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="text-center mb-12">
                            <h2 className="text-3xl font-bold text-black mb-4">Shop by Category</h2>
                            <p className="text-gray-600 max-w-2xl mx-auto">
                                Explore our wide range of product categories
                            </p>
                        </div>

                        <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                            {categories.map((category) => {
                                const IconComponent = category.icon;
                                return (
                                    <Link 
                                        key={category.id} 
                                        href={`/products/category/${category.slug}`}
                                        className="group"
                                    >
                                        <Card className="h-full text-center hover:shadow-md transition-shadow">
                                            <CardContent className="p-6">
                                                <div className="w-12 h-12 mx-auto mb-3 bg-gray-100 rounded-full flex items-center justify-center group-hover:bg-black group-hover:text-white transition-colors">
                                                    <IconComponent className="w-6 h-6" />
                                                </div>
                                                <h3 className="font-semibold text-black mb-1">
                                                    {category.name}
                                                </h3>
                                                <p className="text-sm text-gray-500">
                                                    {category.count} products
                                                </p>
                                            </CardContent>
                                        </Card>
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Footer */}
                <footer className="bg-black text-white py-12">
                    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8">
                            <div>
                                <h3 className="text-xl font-bold mb-4">Store</h3>
                                <p className="text-gray-400 mb-4">
                                    Your trusted destination for quality tech products and exceptional service.
                                </p>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">Quick Links</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li><Link href="/about" className="hover:text-white">About Us</Link></li>
                                    <li><Link href="/contact" className="hover:text-white">Contact</Link></li>
                                    <li><Link href="/faq" className="hover:text-white">FAQ</Link></li>
                                    <li><Link href="/shipping" className="hover:text-white">Shipping Info</Link></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">Legal</h4>
                                <ul className="space-y-2 text-gray-400">
                                    <li><Link href="/privacy" className="hover:text-white">Privacy Policy</Link></li>
                                    <li><Link href="/terms" className="hover:text-white">Terms of Service</Link></li>
                                    <li><Link href="/returns" className="hover:text-white">Returns</Link></li>
                                </ul>
                            </div>
                            
                            <div>
                                <h4 className="font-semibold mb-4">Follow Us</h4>
                                <p className="text-gray-400 mb-4">
                                    Stay connected for updates and special offers.
                                </p>
                                <div className="flex space-x-4">
                                    <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                        <span className="sr-only">Facebook</span>
                                        📘
                                    </Button>
                                    <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                        <span className="sr-only">Twitter</span>
                                        🐦
                                    </Button>
                                    <Button variant="ghost" size="icon" className="text-gray-400 hover:text-white">
                                        <span className="sr-only">Instagram</span>
                                        📷
                                    </Button>
                                </div>
                            </div>
                        </div>
                        
                        <div className="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                            <p>&copy; 2025 Store. All rights reserved.</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}

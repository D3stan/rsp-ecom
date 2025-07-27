import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { 
    ShoppingCart, 
    User, 
    Heart,
    Globe
} from 'lucide-react';
import useTranslation from '@/hooks/useTranslation';

interface HeaderProps {
    currentPage?: 'home' | 'products' | 'about' | 'contact';
    transparent?: boolean;
}

export default function Header({ transparent = false }: HeaderProps) {
    const { auth, name } = usePage<SharedData>().props;
    const { t, locale, changeLocale, isLoading } = useTranslation();

    return (
        <header className={`sticky top-0 z-50 border-b transition-all duration-300 ${
            transparent 
                ? 'border-white/20 bg-transparent backdrop-blur-md' 
                : 'border-gray-200 bg-white/95 backdrop-blur-md'
        }`}>
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="flex items-center justify-between h-16">
                    {/* Logo */}
                    <div className="flex-shrink-0">
                        <Link href="/" className={`text-2xl font-bold ${transparent ? 'text-white' : 'text-black'}`}>
                            {name || 'Store'}
                        </Link>
                    </div>

                    {/* Center Spacer */}
                    <div className="flex-1" />

                    {/* Right Side Actions */}
                    <div className="flex items-center space-x-4">
                        {/* Language & Currency Selectors */}
                        <div className="hidden md:flex items-center space-x-3">
                            {/* Language Selector */}
                            <div className="flex items-center space-x-1">
                                <Globe className={`w-4 h-4 ${transparent ? 'text-white/80' : 'text-gray-600'}`} />
                                <Select value={locale} onValueChange={changeLocale} disabled={isLoading}>
                                    <SelectTrigger className={`w-16 h-8 border-none bg-transparent ${
                                        transparent ? 'text-white hover:bg-white/10' : 'text-gray-700 hover:bg-gray-100'
                                    } ${isLoading ? 'opacity-50 cursor-not-allowed' : ''}`}>
                                        {isLoading ? (
                                            <div className="animate-spin rounded-full h-3 w-3 border border-current border-t-transparent" />
                                        ) : (
                                            <SelectValue />
                                        )}
                                    </SelectTrigger>
                                    <SelectContent className="bg-white border border-gray-200">
                                        <SelectItem value="en" className="text-gray-900 hover:bg-gray-100">EN</SelectItem>
                                        <SelectItem value="es" className="text-gray-900 hover:bg-gray-100">ES</SelectItem>
                                        <SelectItem value="fr" className="text-gray-900 hover:bg-gray-100">FR</SelectItem>
                                        <SelectItem value="de" className="text-gray-900 hover:bg-gray-100">DE</SelectItem>
                                        <SelectItem value="it" className="text-gray-900 hover:bg-gray-100">IT</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        {/* User Actions */}
                        <div className="flex items-center space-x-3">

                            <Button variant="ghost" size="icon" className={`relative ${transparent ? 'text-white hover:bg-white/10' : 'text-black border-gray-200'}`}>
                                <ShoppingCart className="w-5 h-5" />
                                <Badge className="absolute -top-2 -right-2 h-5 w-5 flex items-center justify-center p-0 text-xs">
                                    0
                                </Badge>
                            </Button>
                            {auth.user ? (
                                <>
                                    <Button variant="ghost" size="icon" className={transparent ? 'text-white hover:bg-white/10' : 'text-black'}>
                                        <Heart className="w-5 h-5" />
                                    </Button>
                                    <Link href={route('dashboard')}>
                                        <Button variant="ghost" size="icon" className={transparent ? 'text-white hover:bg-white/10' : ''}>
                                            <User className="w-5 h-5" />
                                        </Button>
                                    </Link>
                                </>
                            ) : (
                                <>
                                    <Link href={route('login')}>
                                        <Button variant={transparent ? "ghost" : "outline"} size="sm" className={transparent ? 'text-white border-white/20 hover:bg-white/10' : ''}>
                                            {t('login')}
                                        </Button>
                                    </Link>
                                    <Link href={route('register')}>
                                        <Button size="sm" className={transparent ? 'bg-white text-black hover:bg-white/90' : 'hover:bg-gray-300'}>
                                            {t('register')}
                                        </Button>
                                    </Link>
                                </>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </header>
    );
}

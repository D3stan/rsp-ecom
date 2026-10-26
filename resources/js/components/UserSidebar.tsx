import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { useTranslation } from '@/hooks/useTranslation';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { ChevronRight, CreditCard, Heart, HelpCircle, LayoutDashboard, LogOut, Menu, Settings, ShoppingBag, User } from 'lucide-react';
import { useState } from 'react';

export const UserSidebar = () => {
    const [isOpen, setIsOpen] = useState(false);
    const { auth } = usePage<SharedData>().props;
    const user = auth.user!;
    const { t } = useTranslation();

    const getInitials = (name: string) => {
        return name
            .split(' ')
            .map((word) => word.charAt(0))
            .join('')
            .toUpperCase()
            .slice(0, 2);
    };

    const menuItems = [
        {
            title: t('dashboard.title'),
            href: route('dashboard'),
            icon: LayoutDashboard,
            active: route().current('dashboard'),
        },
        {
            title: t('orders.title'),
            href: route('orders.index'),
            icon: ShoppingBag,
            active: route().current('orders.*'),
        },
        {
            title: t('wishlist.title'),
            href: route('wishlist.index'),
            icon: Heart,
            active: route().current('wishlist.*'),
        },
        {
            title: t('dashboard.edit_profile'),
            href: route('profile.edit'),
            icon: User,
            active: route().current('profile.*'),
        },
        {
            title: t('dashboard.payment_methods'),
            href: '#',
            icon: CreditCard,
            active: false,
        },
        {
            title: t('dashboard.account_settings'),
            href: route('profile.edit'),
            icon: Settings,
            active: route().current('profile.edit'),
        },
    ];

    const supportItems = [
        {
            title: t('dashboard.get_support'),
            href: route('contact'),
            icon: HelpCircle,
        },
        {
            title: t('faq'),
            href: route('faq'),
            icon: HelpCircle,
        },
    ];

    return (
        <Sheet open={isOpen} onOpenChange={setIsOpen}>
            <SheetTrigger asChild>
                <Button variant="ghost" size="sm" className="md:hidden">
                    <Menu className="h-5 w-5" />
                </Button>
            </SheetTrigger>
            <SheetContent side="right" className="w-80 p-0">
                <SheetHeader className="p-6 pb-0">
                    <SheetTitle className="text-left">{t('dashboard.title')}</SheetTitle>
                </SheetHeader>

                <div className="flex h-full flex-col">
                    {/* User Info */}
                    <div className="p-6">
                        <div className="flex items-center space-x-3">
                            <Avatar className="h-12 w-12">
                                <AvatarImage src={user.avatar} alt={user.name} />
                                <AvatarFallback className="text-sm font-semibold">{getInitials(user.name)}</AvatarFallback>
                            </Avatar>
                            <div className="min-w-0 flex-1">
                                <h3 className="truncate text-sm font-medium">{user.name}</h3>
                                <p className="truncate text-xs text-muted-foreground">{user.email}</p>
                                <div className="mt-1 flex items-center gap-2">
                                    <Badge variant={user.email_verified_at ? 'outline' : 'destructive'} className="px-1 py-0 text-xs">
                                        {user.email_verified_at ? t('dashboard.verified') : t('dashboard.unverified')}
                                    </Badge>
                                    {user.google_id && (
                                        <Badge variant="outline" className="px-1 py-0 text-xs">
                                            {t('nav.google_badge')}
                                        </Badge>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Navigation */}
                    <div className="flex-1 px-3">
                        <nav className="space-y-1">
                            {menuItems.map((item, index) => (
                                <Link
                                    key={index}
                                    href={item.href}
                                    onClick={() => setIsOpen(false)}
                                    className={`flex items-center justify-between rounded-md px-3 py-2 text-sm transition-colors ${
                                        item.active
                                            ? 'bg-primary/10 font-medium text-primary'
                                            : 'text-muted-foreground hover:bg-muted/50 hover:text-foreground'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <item.icon className="h-4 w-4 flex-shrink-0" />
                                        <span className="truncate">{item.title}</span>
                                    </div>
                                    <ChevronRight className="h-3 w-3 flex-shrink-0 opacity-50" />
                                </Link>
                            ))}
                        </nav>

                        {/* Support Section */}
                        <div className="mt-6">
                            <h4 className="mb-2 px-3 text-xs font-medium tracking-wider text-muted-foreground uppercase">{t('support')}</h4>
                            <nav className="space-y-1">
                                {supportItems.map((item, index) => (
                                    <Link
                                        key={index}
                                        href={item.href}
                                        onClick={() => setIsOpen(false)}
                                        className="flex items-center justify-between rounded-md px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
                                    >
                                        <div className="flex items-center gap-3">
                                            <item.icon className="h-4 w-4 flex-shrink-0" />
                                            <span className="truncate">{item.title}</span>
                                        </div>
                                        <ChevronRight className="h-3 w-3 flex-shrink-0 opacity-50" />
                                    </Link>
                                ))}
                            </nav>
                        </div>
                    </div>

                    {/* Logout Button */}
                    <div className="border-t p-3">
                        <Link
                            href={route('logout')}
                            method="post"
                            as="button"
                            onClick={() => setIsOpen(false)}
                            className="flex w-full items-center gap-3 rounded-md px-3 py-2 text-sm text-red-600 transition-colors hover:bg-red-50 hover:text-red-700"
                        >
                            <LogOut className="h-4 w-4" />
                            <span>{t('logout')}</span>
                        </Link>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
};

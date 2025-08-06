import { cn } from '@/lib/utils';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { 
    Settings, 
    CreditCard, 
    Truck, 
    Receipt, 
    Mail 
} from 'lucide-react';

const sidebarNavItems: NavItem[] = [
    {
        title: 'General',
        href: '/admin/settings#general',
        icon: Settings,
    },
    {
        title: 'Payment',
        href: '/admin/settings#payment',
        icon: CreditCard,
    },
    {
        title: 'Shipping',
        href: '/admin/settings#shipping',
        icon: Truck,
    },
    {
        title: 'Tax',
        href: '/admin/settings#tax',
        icon: Receipt,
    },
    {
        title: 'Email',
        href: '/admin/settings#email',
        icon: Mail,
    },
];

interface AdminSettingsLayoutProps extends PropsWithChildren {
    activeTab?: string;
}

export default function AdminSettingsLayout({ children, activeTab = 'general' }: AdminSettingsLayoutProps) {
    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    const currentHash = window.location.hash.replace('#', '') || 'general';

    return (
        <div className="px-4 py-6">
            <div className="mb-8">
                <h1 className="text-3xl font-bold tracking-tight">Settings</h1>
                <p className="text-muted-foreground">
                    Manage your store configuration and preferences
                </p>
            </div>

            <div className="flex flex-col space-y-8 lg:flex-row lg:space-y-0 lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav className="flex flex-col space-y-1 space-x-0">
                        {sidebarNavItems.map((item, index) => {
                            const IconComponent = item.icon;
                            const isActive = currentHash === item.href.split('#')[1];
                            
                            return (
                                <Button
                                    key={`${item.href}-${index}`}
                                    size="sm"
                                    variant="ghost"
                                    asChild
                                    className={cn('w-full justify-start', {
                                        'bg-muted': isActive,
                                    })}
                                >
                                    <Link href={item.href} prefetch>
                                        {IconComponent && <IconComponent className="w-4 h-4 mr-2" />}
                                        {item.title}
                                    </Link>
                                </Button>
                            );
                        })}
                    </nav>
                </aside>

                <Separator className="my-6 md:hidden" />

                <div className="flex-1 md:max-w-4xl">
                    <section className="space-y-8">{children}</section>
                </div>
            </div>
        </div>
    );
}

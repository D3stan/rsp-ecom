import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem, SidebarGroup, SidebarGroupLabel, SidebarGroupContent } from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { 
    BookOpen, 
    Folder, 
    LayoutGrid, 
    Package, 
    ShoppingCart, 
    Users, 
    Settings, 
    BarChart3,
    Tags,
    MessageSquare,
    FileText,
    Plus,
    AlertTriangle,
    Eye
} from 'lucide-react';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: '/admin/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Orders',
        href: '/admin/orders',
        icon: ShoppingCart,
    },
    {
        title: 'Products',
        href: '/admin/products',
        icon: Package,
    },
    {
        title: 'Customers',
        href: '/admin/customers',
        icon: Users,
    },
    {
        title: 'Reviews',
        href: '/admin/reviews',
        icon: MessageSquare,
    },
    {
        title: 'Content',
        href: '/admin/content',
        icon: FileText,
    },
    {
        title: 'Reports',
        href: '/admin/reports',
        icon: BarChart3,
    },
    {
        title: 'Settings',
        href: '/admin/settings',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/react-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#react',
        icon: BookOpen,
    },
];

export function AdminSidebar() {
    const { props } = usePage() as any;
    const pendingOrders = 8; // This would come from props in real implementation
    const lowStockCount = 3; // This would come from props in real implementation

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/admin/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
                
                {/* Quick Actions */}
                <SidebarGroup>
                    <SidebarGroupLabel>Quick Actions</SidebarGroupLabel>
                    <SidebarGroupContent>
                        <div className="space-y-2 px-2">
                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/products/create">
                                    <Plus className="h-4 w-4 mr-2" />
                                    Add Product
                                </Link>
                            </Button>
                            
                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/orders?status=pending">
                                    <AlertTriangle className="h-4 w-4 mr-2" />
                                    Pending ({pendingOrders})
                                </Link>
                            </Button>
                            
                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/products?filter=low-stock">
                                    <Package className="h-4 w-4 mr-2" />
                                    Low Stock ({lowStockCount})
                                </Link>
                            </Button>
                            
                            <Button variant="outline" size="sm" className="w-full justify-start" asChild>
                                <Link href="/admin/reports">
                                    <BarChart3 className="h-4 w-4 mr-2" />
                                    View Reports
                                </Link>
                            </Button>
                        </div>
                    </SidebarGroupContent>
                </SidebarGroup>
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

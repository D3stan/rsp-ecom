import { AdminSidebar } from '@/components/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import FlashToaster from '@/components/flash-toaster';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { url } = usePage();
    const isAdminRoute = url.startsWith('/admin');

    return (
        <AppShell variant="sidebar">
            {isAdminRoute ? <AdminSidebar /> : <AppSidebar />}
            {/* overflow-x-clip, not -hidden: hidden makes these scroll containers and breaks position: sticky inside pages */}
            <AppContent variant="sidebar" className="w-full max-w-full overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {/* admin pages using this layout toast the controller flash like AdminLayout; customer pages keep their behaviour */}
                {isAdminRoute && <FlashToaster />}
                <div className="w-full max-w-full overflow-x-clip">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}

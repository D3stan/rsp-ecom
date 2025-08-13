import { AdminSidebar } from '@/components/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { type BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { type PropsWithChildren } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { url } = usePage();
    const isAdminRoute = url.startsWith('/admin');

    return (
        <AppShell variant="sidebar">
            {isAdminRoute ? <AdminSidebar /> : <AppSidebar />}
            <AppContent variant="sidebar" className="overflow-x-hidden w-full max-w-full">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="w-full max-w-full overflow-x-hidden">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}

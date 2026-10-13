import { AdminSidebar } from '@/components/admin-sidebar';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { useToast } from '@/contexts/ToastContext';
import useTranslation from '@/hooks/useTranslation';
import { type BreadcrumbItem, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, type PropsWithChildren } from 'react';

export default function AdminLayout({ children, breadcrumbs = [] }: PropsWithChildren<{ breadcrumbs?: BreadcrumbItem[] }>) {
    const { flash } = usePage<SharedData>().props;
    const { addToast } = useToast();
    const { t } = useTranslation();

    // Controllers answer with ->with('success'|'error'|'warning', ...): show each message once per visit
    useEffect(() => {
        if (!flash) return;

        (['success', 'error', 'warning'] as const).forEach((type) => {
            const message = flash[type];
            if (message) {
                addToast({ type, title: t(`flash.${type}`), description: message, duration: type === 'success' ? 5000 : 8000 });
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [flash]);

    return (
        <AppShell variant="sidebar">
            <AdminSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
            </AppContent>
        </AppShell>
    );
}

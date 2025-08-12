import AppLayout from '@/layouts/app-layout';
import { Head, usePage } from '@inertiajs/react';
import { PageProps } from '@/types';
import { WelcomeCard } from './components/WelcomeCard';
import { LatestOrderCard } from './components/LatestOrderCard';
import { WishlistCard } from './components/WishlistCard';
import { Button } from '@/components/ui/button';

export default function Dashboard() {
    const { auth } = usePage<PageProps>().props;

    return (
        <AppLayout
            breadcrumbs={[
                {
                    label: 'Dashboard',
                    href: route('dashboard'),
                },
            ]}
        >
            <Head title="Dashboard" />

            <div className="space-y-6">
                <WelcomeCard user={auth.user} />
                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    <LatestOrderCard />
                    <WishlistCard />
                </div>
                 <Button className="w-full">Get Support</Button>
            </div>
        </AppLayout>
    );
}

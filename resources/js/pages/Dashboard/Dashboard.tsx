import AppLayout from '@/layouts/app-layout';
import { Head, Link, usePage } from '@inertiajs/react';
import { type SharedData, type Order } from '@/types';
import { WelcomeCard } from './components/WelcomeCard';
import { LatestOrderCard } from './components/LatestOrderCard';
import { WishlistCard } from './components/WishlistCard';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/useTranslation';
import { 
    ShoppingBag, 
    User, 
    CreditCard, 
    Heart, 
    Settings,
    MessageSquare,
    TrendingUp,
    Clock,
    Star
} from 'lucide-react';

interface DashboardProps {
    recentOrders?: Order[];
    orderStats?: {
        total: number;
        pending: number;
        completed: number;
    };
    wishlistCount?: number;
}

export default function Dashboard({ 
    orderStats = { total: 0, pending: 0, completed: 0 }, 
    wishlistCount = 0 
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user!;
    const { t } = useTranslation();

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    return (
        <AppLayout
            breadcrumbs={[
                {
                    title: t('dashboard.title'),
                    href: route('dashboard'),
                },
            ]}
        >
            <Head title={t('dashboard.title')} />

            <div className="space-y-6 p-4 md:p-6 max-w-7xl mx-auto">
                {/* Welcome Section */}
                <WelcomeCard user={user} />

                {/* Quick Stats */}
                <div className="grid gap-4 grid-cols-1 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.total_orders')}</CardTitle>
                            <ShoppingBag className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{orderStats.total}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('dashboard.all_time_purchases')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.pending_orders')}</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{orderStats.pending}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('dashboard.awaiting_processing')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.completed_orders')}</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{orderStats.completed}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('dashboard.successfully_delivered')}
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">{t('dashboard.wishlist_items')}</CardTitle>
                            <Heart className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{wishlistCount}</div>
                            <p className="text-xs text-muted-foreground">
                                {t('dashboard.saved_for_later')}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Orders and Wishlist */}
                    <div className="lg:col-span-2 space-y-6">
                        <LatestOrderCard />
                        <div className="lg:hidden">
                            <WishlistCard />
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <div className="hidden lg:block">
                            <WishlistCard />
                        </div>

                        {/* Quick Actions */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.quick_actions')}</CardTitle>
                                <CardDescription>
                                    {t('dashboard.quick_actions_description')}
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href={route('profile.edit')}>
                                        <User className="mr-2 h-4 w-4" />
                                        {t('dashboard.edit_profile')}
                                    </Link>
                                </Button>
                                
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href="#" onClick={(e) => e.preventDefault()}>
                                        <Star className="mr-2 h-4 w-4" />
                                        {t('dashboard.write_review')}
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Account Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>{t('dashboard.account_summary')}</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">{t('dashboard.member_since')}</span>
                                    <span className="text-sm font-medium">
                                        {formatDate(user.created_at)}
                                    </span>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">{t('dashboard.email_status')}</span>
                                    <Badge variant={user.email_verified_at ? "outline" : "destructive"}>
                                        {user.email_verified_at ? t('dashboard.verified') : t('dashboard.unverified')}
                                    </Badge>
                                </div>
                                
                                {user.google_id && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">{t('dashboard.google_account')}</span>
                                        <Badge variant="outline">{t('dashboard.connected')}</Badge>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>

                {/* Support Button */}
                <Card>
                    <CardContent className="pt-6">
                        <div className="text-center">
                            <MessageSquare className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                            <h3 className="text-lg font-medium mb-2">{t('dashboard.need_help')}</h3>
                            <p className="text-muted-foreground mb-4">
                                {t('dashboard.support_description')}
                            </p>
                            <Button className="w-full md:w-auto">{t('dashboard.get_support')}</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

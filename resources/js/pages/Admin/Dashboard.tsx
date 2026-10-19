import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { useTranslation } from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    DollarSign,
    Eye,
    Package,
    Plus,
    ShoppingCart,
    TrendingUp,
    Users,
} from 'lucide-react';
import { CartesianGrid, Cell, Line, LineChart, Pie, PieChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface Stats {
    today: {
        orders: number;
        revenue: number;
        customers: number;
    };
    month: {
        orders: number;
        revenue: number;
        customers: number;
    };
    year: {
        orders: number;
        revenue: number;
    };
}

interface Growth {
    orders: number;
    revenue: number;
}

interface RecentOrder {
    id: number;
    order_number: string;
    customer_name: string;
    total_amount: number;
    status: string;
    created_at: string;
    time_ago: string;
}

interface OrderStatusDistribution {
    pending: number;
    processing: number;
    shipped: number;
    delivered: number;
    cancelled: number;
}

interface RevenueChartData {
    date: string;
    revenue: number;
    orders: number;
}

interface Product {
    id: number;
    name: string;
    sales_count?: number;
    stock_quantity?: number;
    price: number;
}

interface Props {
    stats?: Stats;
    growth?: Growth;
    recentOrders?: RecentOrder[];
    orderStatusDistribution?: OrderStatusDistribution;
    revenueChartData?: RevenueChartData[];
    topProducts?: Product[];
    lowStockProducts?: Product[];
}

const COLORS = ['#3B82F6', '#10B981', '#F59E0B', '#EF4444', '#8B5CF6'];

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

export default function AdminDashboard({
    stats,
    growth,
    recentOrders,
    orderStatusDistribution,
    revenueChartData,
    topProducts,
    lowStockProducts,
}: Props) {
    const { t, locale } = useTranslation();

    // Add safety checks and defaults
    const safeStats = stats || {
        today: { orders: 0, revenue: 0, customers: 0 },
        month: { orders: 0, revenue: 0, customers: 0 },
        year: { orders: 0, revenue: 0 },
    };

    const safeGrowth = growth || { orders: 0, revenue: 0 };
    const safeRecentOrders = recentOrders || [];
    const safeOrderStatusDistribution = orderStatusDistribution || {
        pending: 0,
        processing: 0,
        shipped: 0,
        delivered: 0,
        cancelled: 0,
    };
    const safeRevenueChartData = revenueChartData || [];
    const safeTopProducts = topProducts || [];
    const safeLowStockProducts = lowStockProducts || [];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    // Prepare pie chart data
    const pieChartData = Object.entries(safeOrderStatusDistribution).map(([key, value]) => ({
        name: t(`admin.orders.status.${key}`),
        value,
        color: COLORS[Object.keys(safeOrderStatusDistribution).indexOf(key)],
    }));

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.nav.dashboard'), href: '/admin/dashboard' },
            ]}
        >
            <Head title={t('admin.dashboard.title')} />

            <div className="space-y-6 p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">{t('admin.dashboard.title')}</h1>
                        <p className="text-muted-foreground">{t('admin.dashboard.welcome')}</p>
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button size="sm" asChild>
                            <Link href="/admin/orders">
                                <Eye className="mr-2 h-4 w-4" />
                                {t('admin.dashboard.view_orders')}
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <ShoppingCart className="h-5 w-5 text-blue-600" />
                            <h3 className="text-sm font-medium">{t('admin.dashboard.todays_orders')}</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{safeStats.today.orders}</p>
                            <div className={`flex items-center text-xs ${safeGrowth.orders >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {safeGrowth.orders >= 0 ? <ArrowUpRight className="mr-1 h-3 w-3" /> : <ArrowDownRight className="mr-1 h-3 w-3" />}
                                {t('admin.dashboard.from_yesterday', { percent: Math.abs(safeGrowth.orders) })}
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <DollarSign className="h-5 w-5 text-green-600" />
                            <h3 className="text-sm font-medium">{t('admin.dashboard.todays_revenue')}</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{formatCurrency(safeStats.today.revenue)}</p>
                            <div className={`flex items-center text-xs ${safeGrowth.revenue >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {safeGrowth.revenue >= 0 ? <ArrowUpRight className="mr-1 h-3 w-3" /> : <ArrowDownRight className="mr-1 h-3 w-3" />}
                                {t('admin.dashboard.from_yesterday', { percent: Math.abs(safeGrowth.revenue) })}
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <Package className="h-5 w-5 text-purple-600" />
                            <h3 className="text-sm font-medium">{t('admin.dashboard.monthly_orders')}</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{safeStats.month.orders}</p>
                            <div className="flex items-center text-xs text-blue-600">
                                <BarChart3 className="mr-1 h-3 w-3" />
                                {t('admin.dashboard.monthly_revenue', { amount: formatCurrency(safeStats.month.revenue) })}
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <Users className="h-5 w-5 text-orange-600" />
                            <h3 className="text-sm font-medium">{t('admin.dashboard.new_customers')}</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{safeStats.today.customers}</p>
                            <div className="flex items-center text-xs text-green-600">
                                <TrendingUp className="mr-1 h-3 w-3" />
                                {t('admin.dashboard.this_month_count', { count: safeStats.month.customers })}
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Charts Section */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Revenue Chart */}
                    <Card className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold">{t('admin.dashboard.revenue_trend')}</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/reports">
                                    <BarChart3 className="mr-2 h-4 w-4" />
                                    {t('admin.dashboard.view_reports')}
                                </Link>
                            </Button>
                        </div>
                        <div className="h-80">
                            <ResponsiveContainer width="100%" height="100%">
                                <LineChart data={safeRevenueChartData}>
                                    <CartesianGrid strokeDasharray="3 3" />
                                    <XAxis dataKey="date" />
                                    <YAxis />
                                    <Tooltip
                                        formatter={(value: number, name: string) => [
                                            name === 'revenue' ? formatCurrency(value) : value,
                                            name === 'revenue' ? t('admin.dashboard.chart_revenue') : t('admin.dashboard.chart_orders'),
                                        ]}
                                    />
                                    <Line type="monotone" dataKey="revenue" stroke="#3B82F6" strokeWidth={2} dot={{ fill: '#3B82F6' }} />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>

                    {/* Order Status Distribution */}
                    <Card className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold">{t('admin.dashboard.order_status_distribution')}</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/orders">
                                    <Eye className="mr-2 h-4 w-4" />
                                    {t('admin.dashboard.manage_orders')}
                                </Link>
                            </Button>
                        </div>
                        <div className="h-80">
                            <ResponsiveContainer width="100%" height="100%">
                                <PieChart>
                                    <Pie
                                        data={pieChartData}
                                        cx="50%"
                                        cy="50%"
                                        outerRadius={80}
                                        fill="#8884d8"
                                        dataKey="value"
                                        label={({ name, value }) => `${name}: ${value}`}
                                    >
                                        {pieChartData.map((entry, index) => (
                                            <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                                        ))}
                                    </Pie>
                                    <Tooltip />
                                </PieChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>
                </div>

                {/* Bottom Section */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Recent Orders */}
                    <Card className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold">{t('admin.dashboard.recent_orders')}</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/orders">{t('admin.dashboard.view_all')}</Link>
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {safeRecentOrders.map((order) => (
                                <div key={order.id} className="flex items-center justify-between rounded-lg border p-3">
                                    <div className="flex-1">
                                        <div className="flex items-center space-x-2">
                                            <p className="text-sm font-medium">{order.order_number}</p>
                                            <Badge
                                                className={`text-xs ${statusColors[order.status as keyof typeof statusColors]}`}
                                                variant="secondary"
                                            >
                                                {t(`admin.orders.status.${order.status}`)}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">{order.customer_name}</p>
                                        <p className="text-xs text-muted-foreground">{order.time_ago}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-sm font-medium">{formatCurrency(order.total_amount)}</p>
                                        <Button variant="ghost" size="sm" asChild>
                                            <Link href={`/admin/orders/${order.id}`}>
                                                <Eye className="h-3 w-3" />
                                            </Link>
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </Card>

                    {/* Top Products */}
                    <Card className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="text-lg font-semibold">{t('admin.dashboard.top_products')}</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/products">{t('admin.dashboard.view_all')}</Link>
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {safeTopProducts.length > 0 ? (
                                safeTopProducts.map((product) => (
                                    <div key={product.id} className="flex items-center justify-between rounded-lg border p-3">
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">{product.name}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {t('admin.dashboard.sales_count', { count: product.sales_count ?? 0 })}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-sm font-medium">{formatCurrency(product.price)}</p>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="py-4 text-center text-sm text-muted-foreground">{t('admin.dashboard.no_sales_data')}</p>
                            )}
                        </div>
                    </Card>

                    {/* Low Stock Alerts */}
                    <Card className="p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="flex items-center text-lg font-semibold">
                                <AlertTriangle className="mr-2 h-5 w-5 text-orange-500" />
                                {t('admin.dashboard.low_stock_alerts')}
                            </h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/products">
                                    <Plus className="mr-2 h-4 w-4" />
                                    {t('admin.dashboard.restock')}
                                </Link>
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {safeLowStockProducts.length > 0 ? (
                                safeLowStockProducts.map((product) => (
                                    <div key={product.id} className="flex items-center justify-between rounded-lg border bg-orange-50 p-3">
                                        <div className="flex-1">
                                            <p className="text-sm font-medium">{product.name}</p>
                                            <p className="text-xs text-orange-600">
                                                {t('admin.dashboard.only_left_in_stock', { count: product.stock_quantity ?? 0 })}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <Badge variant="outline" className="border-orange-600 text-orange-600">
                                                {product.stock_quantity}
                                            </Badge>
                                        </div>
                                    </div>
                                ))
                            ) : (
                                <p className="py-4 text-center text-sm text-muted-foreground">{t('admin.dashboard.all_well_stocked')}</p>
                            )}
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

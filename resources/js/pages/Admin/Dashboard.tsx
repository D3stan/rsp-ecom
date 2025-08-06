import AppLayout from '@/layouts/app-layout';
import { Card } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Head, Link } from '@inertiajs/react';
import { 
    ShoppingCart, 
    DollarSign, 
    Package, 
    Users,
    TrendingUp,
    TrendingDown,
    AlertTriangle,
    Eye,
    Plus,
    RefreshCw,
    BarChart3,
    ArrowUpRight,
    ArrowDownRight
} from 'lucide-react';
import { LineChart, Line, XAxis, YAxis, CartesianGrid, Tooltip, ResponsiveContainer, PieChart, Pie, Cell, BarChart, Bar } from 'recharts';

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
    lowStockProducts
}: Props) {
    // Add safety checks and defaults
    const safeStats = stats || {
        today: { orders: 0, revenue: 0, customers: 0 },
        month: { orders: 0, revenue: 0, customers: 0 },
        year: { orders: 0, revenue: 0 }
    };
    
    const safeGrowth = growth || { orders: 0, revenue: 0 };
    const safeRecentOrders = recentOrders || [];
    const safeOrderStatusDistribution = orderStatusDistribution || {
        pending: 0, processing: 0, shipped: 0, delivered: 0, cancelled: 0
    };
    const safeRevenueChartData = revenueChartData || [];
    const safeTopProducts = topProducts || [];
    const safeLowStockProducts = lowStockProducts || [];

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount);
    };

    // Prepare pie chart data
    const pieChartData = Object.entries(safeOrderStatusDistribution).map(([key, value]) => ({
        name: key.charAt(0).toUpperCase() + key.slice(1),
        value,
        color: COLORS[Object.keys(safeOrderStatusDistribution).indexOf(key)]
    }));

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Dashboard', href: '/admin/dashboard' },
            ]}
        >
            <Head title="Admin Dashboard" />

            <div className="space-y-6 p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Admin Dashboard</h1>
                        <p className="text-muted-foreground">
                            Welcome to your admin dashboard
                        </p>
                    </div>
                    <div className="flex items-center space-x-2">
                        <Button variant="outline" size="sm">
                            <RefreshCw className="h-4 w-4 mr-2" />
                            Refresh
                        </Button>
                        <Button size="sm" asChild>
                            <Link href="/admin/orders">
                                <Eye className="h-4 w-4 mr-2" />
                                View Orders
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <ShoppingCart className="h-5 w-5 text-blue-600" />
                            <h3 className="text-sm font-medium">Today's Orders</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{safeStats.today.orders}</p>
                            <div className={`flex items-center text-xs ${safeGrowth.orders >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {safeGrowth.orders >= 0 ? (
                                    <ArrowUpRight className="h-3 w-3 mr-1" />
                                ) : (
                                    <ArrowDownRight className="h-3 w-3 mr-1" />
                                )}
                                {Math.abs(safeGrowth.orders)}% from yesterday
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <DollarSign className="h-5 w-5 text-green-600" />
                            <h3 className="text-sm font-medium">Today's Revenue</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{formatCurrency(safeStats.today.revenue)}</p>
                            <div className={`flex items-center text-xs ${safeGrowth.revenue >= 0 ? 'text-green-600' : 'text-red-600'}`}>
                                {safeGrowth.revenue >= 0 ? (
                                    <ArrowUpRight className="h-3 w-3 mr-1" />
                                ) : (
                                    <ArrowDownRight className="h-3 w-3 mr-1" />
                                )}
                                {Math.abs(safeGrowth.revenue)}% from yesterday
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <Package className="h-5 w-5 text-purple-600" />
                            <h3 className="text-sm font-medium">Monthly Orders</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{safeStats.month.orders}</p>
                            <div className="flex items-center text-xs text-blue-600">
                                <BarChart3 className="h-3 w-3 mr-1" />
                                {formatCurrency(safeStats.month.revenue)} revenue
                            </div>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <Users className="h-5 w-5 text-orange-600" />
                            <h3 className="text-sm font-medium">New Customers</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{safeStats.today.customers}</p>
                            <div className="flex items-center text-xs text-green-600">
                                <TrendingUp className="h-3 w-3 mr-1" />
                                {safeStats.month.customers} this month
                            </div>
                        </div>
                    </Card>
                </div>

                {/* Charts Section */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Revenue Chart */}
                    <Card className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold">Revenue Trend (7 Days)</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/reports">
                                    <BarChart3 className="h-4 w-4 mr-2" />
                                    View Reports
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
                                            name === 'revenue' ? 'Revenue' : 'Orders'
                                        ]}
                                    />
                                    <Line 
                                        type="monotone" 
                                        dataKey="revenue" 
                                        stroke="#3B82F6" 
                                        strokeWidth={2}
                                        dot={{ fill: '#3B82F6' }}
                                    />
                                </LineChart>
                            </ResponsiveContainer>
                        </div>
                    </Card>

                    {/* Order Status Distribution */}
                    <Card className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold">Order Status Distribution</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/orders">
                                    <Eye className="h-4 w-4 mr-2" />
                                    Manage Orders
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
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold">Recent Orders</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/orders">
                                    View All
                                </Link>
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {safeRecentOrders.map((order) => (
                                <div key={order.id} className="flex items-center justify-between p-3 border rounded-lg">
                                    <div className="flex-1">
                                        <div className="flex items-center space-x-2">
                                            <p className="font-medium text-sm">{order.order_number}</p>
                                            <Badge
                                                className={`text-xs ${statusColors[order.status as keyof typeof statusColors]}`}
                                                variant="secondary"
                                            >
                                                {order.status}
                                            </Badge>
                                        </div>
                                        <p className="text-xs text-muted-foreground">{order.customer_name}</p>
                                        <p className="text-xs text-muted-foreground">{order.time_ago}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium text-sm">{formatCurrency(order.total_amount)}</p>
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
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold">Top Products</h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/products">
                                    View All
                                </Link>
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {safeTopProducts.length > 0 ? safeTopProducts.map((product) => (
                                <div key={product.id} className="flex items-center justify-between p-3 border rounded-lg">
                                    <div className="flex-1">
                                        <p className="font-medium text-sm">{product.name}</p>
                                        <p className="text-xs text-muted-foreground">
                                            {product.sales_count} sales
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <p className="font-medium text-sm">{formatCurrency(product.price)}</p>
                                    </div>
                                </div>
                            )) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    No sales data available
                                </p>
                            )}
                        </div>
                    </Card>

                    {/* Low Stock Alerts */}
                    <Card className="p-6">
                        <div className="flex items-center justify-between mb-4">
                            <h2 className="text-lg font-semibold flex items-center">
                                <AlertTriangle className="h-5 w-5 text-orange-500 mr-2" />
                                Low Stock Alerts
                            </h2>
                            <Button variant="outline" size="sm" asChild>
                                <Link href="/admin/products">
                                    <Plus className="h-4 w-4 mr-2" />
                                    Restock
                                </Link>
                            </Button>
                        </div>
                        <div className="space-y-3">
                            {safeLowStockProducts.length > 0 ? safeLowStockProducts.map((product) => (
                                <div key={product.id} className="flex items-center justify-between p-3 border rounded-lg bg-orange-50">
                                    <div className="flex-1">
                                        <p className="font-medium text-sm">{product.name}</p>
                                        <p className="text-xs text-orange-600">
                                            Only {product.stock_quantity} left in stock
                                        </p>
                                    </div>
                                    <div className="text-right">
                                        <Badge variant="outline" className="text-orange-600 border-orange-600">
                                            {product.stock_quantity}
                                        </Badge>
                                    </div>
                                </div>
                            )) : (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    All products are well stocked
                                </p>
                            )}
                        </div>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}

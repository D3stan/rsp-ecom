import AppLayout from '@/layouts/app-layout';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select } from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/react';
import { 
    Search, 
    Filter, 
    Download, 
    Eye, 
    Edit,
    MoreHorizontal,
    TrendingUp,
    DollarSign,
    Package,
    Users
} from 'lucide-react';
import { useState } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';

interface Order {
    id: number;
    order_number: string;
    user?: {
        id: number;
        name: string;
        email: string;
    };
    guest_email?: string;
    status: string;
    payment_status: string;
    total_amount: number;
    created_at: string;
    orderItems?: Array<{
        id: number;
        product: {
            id: string | number;
            name: string;
        };
        quantity: number;
    }>;
}

interface KPIs {
    today: {
        orders: number;
        revenue: number;
    };
    month: {
        orders: number;
        revenue: number;
    };
    year: {
        orders: number;
        revenue: number;
    };
}

interface Props {
    orders: {
        data: Order[];
        links: any[];
        meta: any;
    };
    kpis: KPIs;
    filters: {
        status?: string;
        payment_status?: string;
        search?: string;
        date_from?: string;
        date_to?: string;
    };
}

const statusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    shipped: 'bg-purple-100 text-purple-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
};

const paymentStatusColors = {
    pending: 'bg-yellow-100 text-yellow-800',
    processing: 'bg-blue-100 text-blue-800',
    succeeded: 'bg-green-100 text-green-800',
    failed: 'bg-red-100 text-red-800',
};

export default function OrdersIndex({ orders, kpis, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [paymentStatusFilter, setPaymentStatusFilter] = useState(filters.payment_status || '');

    const handleFilter = () => {
        router.get('/admin/orders', {
            search: search || undefined,
            status: statusFilter || undefined,
            payment_status: paymentStatusFilter || undefined,
        }, { preserveState: true });
    };

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('');
        setPaymentStatusFilter('');
        router.get('/admin/orders');
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    const getCustomerInfo = (order: Order) => {
        if (order.user) {
            return {
                name: order.user.name,
                email: order.user.email,
            };
        }
        return {
            name: 'Guest',
            email: order.guest_email || 'N/A',
        };
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Orders', href: '/admin/orders' },
            ]}
        >
            <Head title="Orders - Admin" />

            <div className="space-y-6 p-6">
                {/* Page Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Orders</h1>
                        <p className="text-muted-foreground">
                            Manage and track all orders
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="sm">
                            <Download className="h-4 w-4 mr-2" />
                            Export
                        </Button>
                    </div>
                </div>

                {/* KPI Cards */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <Package className="h-5 w-5 text-blue-600" />
                            <h3 className="text-sm font-medium">Today's Orders</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{kpis.today.orders}</p>
                            <p className="text-xs text-muted-foreground">
                                {formatCurrency(kpis.today.revenue)} revenue
                            </p>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <TrendingUp className="h-5 w-5 text-green-600" />
                            <h3 className="text-sm font-medium">This Month</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{kpis.month.orders}</p>
                            <p className="text-xs text-muted-foreground">
                                {formatCurrency(kpis.month.revenue)} revenue
                            </p>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <DollarSign className="h-5 w-5 text-yellow-600" />
                            <h3 className="text-sm font-medium">This Year</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{kpis.year.orders}</p>
                            <p className="text-xs text-muted-foreground">
                                {formatCurrency(kpis.year.revenue)} revenue
                            </p>
                        </div>
                    </Card>

                    <Card className="p-6">
                        <div className="flex items-center space-x-2">
                            <Users className="h-5 w-5 text-purple-600" />
                            <h3 className="text-sm font-medium">Total Orders</h3>
                        </div>
                        <div className="mt-2">
                            <p className="text-2xl font-bold">{orders.meta.total}</p>
                            <p className="text-xs text-muted-foreground">
                                All time
                            </p>
                        </div>
                    </Card>
                </div>

                {/* Filters */}
                <Card className="p-6">
                    <div className="flex flex-col md:flex-row gap-4">
                        <div className="flex-1">
                            <div className="relative">
                                <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                                <Input
                                    placeholder="Search orders, customers..."
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-10"
                                />
                            </div>
                        </div>
                        <div className="flex gap-2">
                            <select
                                value={statusFilter}
                                onChange={(e) => setStatusFilter(e.target.value)}
                                className="px-3 py-2 border border-input bg-background rounded-md text-sm"
                            >
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="shipped">Shipped</option>
                                <option value="delivered">Delivered</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                            <select
                                value={paymentStatusFilter}
                                onChange={(e) => setPaymentStatusFilter(e.target.value)}
                                className="px-3 py-2 border border-input bg-background rounded-md text-sm"
                            >
                                <option value="">All Payment Status</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="succeeded">Succeeded</option>
                                <option value="failed">Failed</option>
                            </select>
                            <Button onClick={handleFilter} variant="outline" size="sm">
                                <Filter className="h-4 w-4 mr-2" />
                                Filter
                            </Button>
                            {(search || statusFilter || paymentStatusFilter) && (
                                <Button onClick={clearFilters} variant="ghost" size="sm">
                                    Clear
                                </Button>
                            )}
                        </div>
                    </div>
                </Card>

                {/* Orders Table */}
                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="text-left p-4 font-medium">Order</th>
                                    <th className="text-left p-4 font-medium">Customer</th>
                                    <th className="text-left p-4 font-medium">Status</th>
                                    <th className="text-left p-4 font-medium">Payment</th>
                                    <th className="text-left p-4 font-medium">Total</th>
                                    <th className="text-left p-4 font-medium">Date</th>
                                    <th className="text-right p-4 font-medium">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {orders.data.map((order) => {
                                    const customer = getCustomerInfo(order);
                                    return (
                                        <tr key={order.id} className="border-b hover:bg-muted/50">
                                            <td className="p-4">
                                                <div>
                                                    <Link
                                                        href={`/admin/orders/${order.id}`}
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {order.order_number}
                                                    </Link>
                                                    <p className="text-sm text-muted-foreground">
                                                        {order.orderItems?.length || 0} items
                                                    </p>
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <div>
                                                    <p className="font-medium">{customer.name}</p>
                                                    <p className="text-sm text-muted-foreground">{customer.email}</p>
                                                </div>
                                            </td>
                                            <td className="p-4">
                                                <Badge
                                                    className={statusColors[order.status as keyof typeof statusColors]}
                                                    variant="secondary"
                                                >
                                                    {order.status}
                                                </Badge>
                                            </td>
                                            <td className="p-4">
                                                <Badge
                                                    className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                                    variant="secondary"
                                                >
                                                    {order.payment_status}
                                                </Badge>
                                            </td>
                                            <td className="p-4">
                                                <span className="font-medium">
                                                    {formatCurrency(order.total_amount)}
                                                </span>
                                            </td>
                                            <td className="p-4">
                                                <span className="text-sm">{formatDate(order.created_at)}</span>
                                            </td>
                                            <td className="p-4 text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/orders/${order.id}`}>
                                                                <Eye className="h-4 w-4 mr-2" />
                                                                View Details
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/orders/${order.id}/edit`}>
                                                                <Edit className="h-4 w-4 mr-2" />
                                                                Edit Order
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem className="text-red-600">
                                                            Cancel Order
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    {/* Pagination */}
                    {orders.meta.last_page > 1 && (
                        <div className="px-6 py-4 border-t">
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Showing {orders.meta.from} to {orders.meta.to} of {orders.meta.total} results
                                </p>
                                <div className="flex items-center space-x-2">
                                    {orders.links.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? "default" : "outline"}
                                            size="sm"
                                            onClick={() => link.url && router.get(link.url)}
                                            disabled={!link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AppLayout>
    );
}

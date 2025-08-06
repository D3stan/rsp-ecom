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
    Users,
    ChevronDown,
    ChevronUp
} from 'lucide-react';
import { useState, useEffect } from 'react';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';

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
    orders?: {
        data?: Order[];
        links?: any[];
        meta?: {
            total: number;
            per_page: number;
            current_page: number;
            last_page: number;
            from: number;
            to: number;
        };
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
    const [isKPIsOpen, setIsKPIsOpen] = useState(false);

    // Add safety checks for orders data
    const ordersData = orders?.data || [];
    const ordersMeta = orders?.meta || { total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 };
    const ordersLinks = orders?.links || [];

    // Instant filtering - apply filters immediately on change
    useEffect(() => {
        const timeoutId = setTimeout(() => {
            router.get('/admin/orders', {
                search: search || undefined,
                status: statusFilter || undefined,
                payment_status: paymentStatusFilter || undefined,
            }, { 
                preserveState: true,
                preserveScroll: true,
                replace: true
            });
        }, 300); // 300ms debounce for search

        return () => clearTimeout(timeoutId);
    }, [search, statusFilter, paymentStatusFilter]);

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('');
        setPaymentStatusFilter('');
        router.get('/admin/orders');
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('it-IT', {
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

            <div className="space-y-4 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl md:text-3xl font-bold tracking-tight">Orders</h1>
                        <p className="text-sm md:text-base text-muted-foreground">
                            Manage and track all orders
                        </p>
                    </div>
                </div>

                {/* Collapsible KPI Cards */}
                <Collapsible open={isKPIsOpen} onOpenChange={setIsKPIsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button 
                            variant="outline" 
                            className="w-full justify-between"
                            size="sm"
                        >
                            <span className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" />
                                Quick Stats
                            </span>
                            {isKPIsOpen ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="space-y-4 mt-4">
                        <div className="grid gap-3 grid-cols-2 lg:grid-cols-4">
                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Package className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">Today</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.today.orders}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatCurrency(kpis.today.revenue)}
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <TrendingUp className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">Month</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.month.orders}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatCurrency(kpis.month.revenue)}
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <DollarSign className="h-4 w-4 text-yellow-600" />
                                    <h3 className="text-xs font-medium">Year</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.year.orders}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatCurrency(kpis.year.revenue)}
                                    </p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="flex items-center space-x-2 mb-2">
                                    <Users className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">Total</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{ordersMeta.total}</p>
                                    <p className="text-xs text-muted-foreground">
                                        All time
                                    </p>
                                </div>
                            </Card>
                        </div>
                    </CollapsibleContent>
                </Collapsible>

                {/* Mobile-First Filters */}
                <Card className="p-4">
                    <div className="space-y-3">
                        {/* Search Bar */}
                        <div className="relative">
                            <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search orders, customers..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10"
                            />
                        </div>
                        
                        {/* Filter Dropdowns */}
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">
                                    Order Status
                                </label>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="w-full px-3 py-2 border border-input bg-background rounded-md text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="shipped">Shipped</option>
                                    <option value="delivered">Delivered</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            
                            <div>
                                <label className="text-xs font-medium text-muted-foreground mb-1 block">
                                    Payment Status
                                </label>
                                <select
                                    value={paymentStatusFilter}
                                    onChange={(e) => setPaymentStatusFilter(e.target.value)}
                                    className="w-full px-3 py-2 border border-input bg-background rounded-md text-sm"
                                >
                                    <option value="">All Payment Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="processing">Processing</option>
                                    <option value="succeeded">Succeeded</option>
                                    <option value="failed">Failed</option>
                                </select>
                            </div>
                            
                            <div className="flex items-end">
                                {(search || statusFilter || paymentStatusFilter) && (
                                    <Button 
                                        onClick={clearFilters} 
                                        variant="outline" 
                                        size="sm"
                                        className="w-full"
                                    >
                                        Clear Filters
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Mobile-First Orders Table */}
                <Card>
                    {/* Desktop Table View */}
                    <div className="hidden md:block overflow-x-auto">
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
                                {ordersData.length > 0 ? ordersData.map((order) => {
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
                                }) : (
                                    <tr>
                                        <td colSpan={7} className="text-center py-8 text-muted-foreground">
                                            No orders found
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card View */}
                    <div className="md:hidden space-y-3 p-3">
                        {ordersData.length > 0 ? ordersData.map((order) => {
                            const customer = getCustomerInfo(order);
                            return (
                                <Card key={order.id} className="p-4">
                                    <div className="flex items-start justify-between mb-3">
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
                                        <div className="flex gap-2">
                                            <Badge
                                                className={statusColors[order.status as keyof typeof statusColors]}
                                                variant="secondary"
                                            >
                                                {order.status}
                                            </Badge>
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
                                        </div>
                                    </div>
                                    
                                    <div className="space-y-2">
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Customer:</span>
                                            <div className="text-right">
                                                <p className="font-medium text-sm">{customer.name}</p>
                                                <p className="text-xs text-muted-foreground">{customer.email}</p>
                                            </div>
                                        </div>
                                        
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Payment:</span>
                                            <Badge
                                                className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                                variant="secondary"
                                            >
                                                {order.payment_status}
                                            </Badge>
                                        </div>
                                        
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Total:</span>
                                            <span className="font-bold">
                                                {formatCurrency(order.total_amount)}
                                            </span>
                                        </div>
                                        
                                        <div className="flex justify-between items-center">
                                            <span className="text-sm text-muted-foreground">Date:</span>
                                            <span className="text-sm">{formatDate(order.created_at)}</span>
                                        </div>
                                    </div>
                                </Card>
                            );
                        }) : (
                            <div className="text-center py-8 text-muted-foreground">
                                No orders found
                            </div>
                        )}
                    </div>

                    {/* Mobile-First Pagination */}
                    {ordersMeta.last_page > 1 && (
                        <div className="px-4 py-4 border-t">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-sm text-muted-foreground text-center md:text-left">
                                    Showing {ordersMeta.from} to {ordersMeta.to} of {ordersMeta.total} results
                                </p>
                                <div className="flex items-center justify-center space-x-1 md:space-x-2">
                                    {ordersLinks.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? "default" : "outline"}
                                            size="sm"
                                            onClick={() => link.url && router.get(link.url)}
                                            disabled={!link.url}
                                            dangerouslySetInnerHTML={{ __html: link.label }}
                                            className="h-8 min-w-[32px]"
                                        />
                                    ))}
                                </div>
                            </div>
                        </div>
                    )}
                </Card>
                
                {/* Mobile Bottom Navigation */}
                <div className="md:hidden fixed bottom-0 left-0 right-0 bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 border-t z-50">
                    <div className="safe-area-inset-bottom">
                        <div className="flex justify-center gap-2 p-3">
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => setIsKPIsOpen(!isKPIsOpen)}
                                className="flex-1 h-12 text-xs"
                            >
                                <TrendingUp className="h-4 w-4 mr-1" />
                                Stats
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm"
                                onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                                className="flex-1 h-12 text-xs"
                            >
                                <Filter className="h-4 w-4 mr-1" />
                                Filters
                            </Button>
                            <Button 
                                variant="outline" 
                                size="sm"
                                asChild
                                className="flex-1 h-12 text-xs"
                            >
                                <Link href="/admin/dashboard">
                                    <Package className="h-4 w-4 mr-1" />
                                    Dashboard
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
                
            </div>
        </AppLayout>
    );
}

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronDown, ChevronUp, DollarSign, Edit, Eye, Filter, MoreHorizontal, Package, Search, TrendingUp, Truck, Users } from 'lucide-react';
import { useEffect, useState } from 'react';

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
    // statuses this order may move to next (server-side order lifecycle)
    allowed_transitions?: string[];
    // processing and paid: the server accepts the ship form (Order::canShip)
    can_ship?: boolean;
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

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Props {
    orders?: {
        data?: Order[];
        links?: PaginationLink[];
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
        // "1": only cancelled orders whose money is still captured
        refund_due?: string;
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
    partially_refunded: 'bg-orange-100 text-orange-800',
    refunded: 'bg-gray-100 text-gray-800',
};

export default function OrdersIndex({ orders, kpis, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState(filters.status || '');
    const [paymentStatusFilter, setPaymentStatusFilter] = useState(filters.payment_status || '');
    const [refundDueFilter, setRefundDueFilter] = useState(filters.refund_due === '1');
    const { t, locale } = useTranslation();
    const [isKPIsOpen, setIsKPIsOpen] = useState(false);

    // Add safety checks for orders data
    const ordersData = orders?.data || [];
    const ordersMeta = orders?.meta || { total: 0, per_page: 15, current_page: 1, last_page: 1, from: 0, to: 0 };
    const ordersLinks = orders?.links || [];

    // Instant filtering - apply filters immediately on change
    useEffect(() => {
        // Pagination remounts the page: without this check the effect would reload it without `page` (back to page 1)
        if (
            search === (filters.search || '') &&
            statusFilter === (filters.status || '') &&
            paymentStatusFilter === (filters.payment_status || '') &&
            refundDueFilter === (filters.refund_due === '1')
        ) {
            return;
        }

        const timeoutId = setTimeout(() => {
            router.get(
                '/admin/orders',
                {
                    search: search || undefined,
                    status: statusFilter || undefined,
                    payment_status: paymentStatusFilter || undefined,
                    refund_due: refundDueFilter ? 1 : undefined,
                },
                {
                    preserveState: true,
                    preserveScroll: true,
                    replace: true,
                },
            );
        }, 300); // 300ms debounce for search

        return () => clearTimeout(timeoutId);
    }, [search, statusFilter, paymentStatusFilter, refundDueFilter]);

    const clearFilters = () => {
        setSearch('');
        setStatusFilter('');
        setPaymentStatusFilter('');
        setRefundDueFilter(false);
        router.get('/admin/orders');
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat(locale, {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString(locale, {
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
            name: t('admin.orders.guest'),
            email: order.guest_email || t('admin.orders.not_available'),
        };
    };

    return (
        <AppLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('admin.orders.title'), href: '/admin/orders' },
            ]}
        >
            <Head title={t('admin.orders.head_title')} />

            <div className="space-y-4 p-4 md:p-6">
                {/* Mobile-First Page Header */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('admin.orders.title')}</h1>
                        <p className="text-sm text-muted-foreground md:text-base">{t('admin.orders.manage_description')}</p>
                    </div>
                </div>

                {/* Collapsible KPI Cards */}
                <Collapsible open={isKPIsOpen} onOpenChange={setIsKPIsOpen}>
                    <CollapsibleTrigger asChild>
                        <Button variant="outline" className="w-full justify-between" size="sm">
                            <span className="flex items-center gap-2">
                                <TrendingUp className="h-4 w-4" />
                                {t('admin.orders.kpi.quick_stats')}
                            </span>
                            {isKPIsOpen ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent className="mt-4 space-y-4">
                        <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Package className="h-4 w-4 text-blue-600" />
                                    <h3 className="text-xs font-medium">{t('admin.orders.kpi.today')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.today.orders}</p>
                                    <p className="text-xs text-muted-foreground">{formatCurrency(kpis.today.revenue)}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <TrendingUp className="h-4 w-4 text-green-600" />
                                    <h3 className="text-xs font-medium">{t('admin.orders.kpi.month')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.month.orders}</p>
                                    <p className="text-xs text-muted-foreground">{formatCurrency(kpis.month.revenue)}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <DollarSign className="h-4 w-4 text-yellow-600" />
                                    <h3 className="text-xs font-medium">{t('admin.orders.kpi.year')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{kpis.year.orders}</p>
                                    <p className="text-xs text-muted-foreground">{formatCurrency(kpis.year.revenue)}</p>
                                </div>
                            </Card>

                            <Card className="p-4">
                                <div className="mb-2 flex items-center space-x-2">
                                    <Users className="h-4 w-4 text-purple-600" />
                                    <h3 className="text-xs font-medium">{t('admin.orders.kpi.total')}</h3>
                                </div>
                                <div>
                                    <p className="text-lg font-bold">{ordersMeta.total}</p>
                                    <p className="text-xs text-muted-foreground">{t('admin.orders.kpi.all_time')}</p>
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
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 transform text-muted-foreground" />
                            <Input
                                placeholder={t('admin.orders.search_placeholder')}
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="pl-10"
                            />
                        </div>

                        {/* Filter Dropdowns */}
                        <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">{t('admin.orders.filter_status')}</label>
                                <select
                                    value={statusFilter}
                                    onChange={(e) => setStatusFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">{t('admin.orders.status_all')}</option>
                                    <option value="pending">{t('admin.orders.status.pending')}</option>
                                    <option value="processing">{t('admin.orders.status.processing')}</option>
                                    <option value="shipped">{t('admin.orders.status.shipped')}</option>
                                    <option value="delivered">{t('admin.orders.status.delivered')}</option>
                                    <option value="cancelled">{t('admin.orders.status.cancelled')}</option>
                                </select>
                            </div>

                            <div>
                                <label className="mb-1 block text-xs font-medium text-muted-foreground">{t('admin.orders.filter_payment_status')}</label>
                                <select
                                    value={paymentStatusFilter}
                                    onChange={(e) => setPaymentStatusFilter(e.target.value)}
                                    className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">{t('admin.orders.payment_status_all')}</option>
                                    <option value="pending">{t('admin.orders.payment_status.pending')}</option>
                                    <option value="processing">{t('admin.orders.payment_status.processing')}</option>
                                    <option value="succeeded">{t('admin.orders.payment_status.succeeded')}</option>
                                    <option value="failed">{t('admin.orders.payment_status.failed')}</option>
                                    <option value="partially_refunded">{t('admin.orders.payment_status.partially_refunded')}</option>
                                    <option value="refunded">{t('admin.orders.payment_status.refunded')}</option>
                                </select>
                            </div>

                            <div className="flex items-end gap-3">
                                <label className="flex h-9 items-center gap-2 text-sm whitespace-nowrap">
                                    <input type="checkbox" checked={refundDueFilter} onChange={(e) => setRefundDueFilter(e.target.checked)} />
                                    {t('admin.orders.refund_due')}
                                </label>
                                {(search || statusFilter || paymentStatusFilter || refundDueFilter) && (
                                    <Button onClick={clearFilters} variant="outline" size="sm" className="w-full">
                                        {t('admin.orders.clear_filters')}
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* Mobile-First Orders Table */}
                <Card>
                    {/* Desktop Table View */}
                    <div className="hidden overflow-x-auto md:block">
                        <table className="w-full">
                            <thead>
                                <tr className="border-b">
                                    <th className="p-4 text-left font-medium">{t('admin.orders.col_order')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.orders.col_customer')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.orders.col_status')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.orders.col_payment')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.orders.col_total')}</th>
                                    <th className="p-4 text-left font-medium">{t('admin.orders.col_date')}</th>
                                    <th className="p-4 text-right font-medium">{t('admin.orders.col_actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {ordersData.length > 0 ? (
                                    ordersData.map((order) => {
                                        const customer = getCustomerInfo(order);
                                        return (
                                            <tr key={order.id} className="border-b hover:bg-muted/50">
                                                <td className="p-4">
                                                    <div>
                                                        <Link href={`/admin/orders/${order.id}`} className="font-medium text-primary hover:underline">
                                                            {order.order_number}
                                                        </Link>
                                                        <p className="text-sm text-muted-foreground">
                                                            {t('admin.orders.items_count', { count: order.orderItems?.length || 0 })}
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
                                                    <Badge className={statusColors[order.status as keyof typeof statusColors]} variant="secondary">
                                                        {t(`admin.orders.status.${order.status}`)}
                                                    </Badge>
                                                </td>
                                                <td className="p-4">
                                                    <Badge
                                                        className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                                        variant="secondary"
                                                    >
                                                        {t(`admin.orders.payment_status.${order.payment_status}`)}
                                                    </Badge>
                                                </td>
                                                <td className="p-4">
                                                    <span className="font-medium">{formatCurrency(order.total_amount)}</span>
                                                </td>
                                                <td className="p-4">
                                                    <span className="text-sm">{formatDate(order.created_at)}</span>
                                                </td>
                                                <td className="p-4 text-right">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {order.can_ship && (
                                                            <Button asChild size="sm" variant="outline">
                                                                <Link href={`/admin/orders/${order.id}/ship`}>
                                                                    <Truck className="mr-1 h-4 w-4" />
                                                                    {t('admin.orders.ship.button')}
                                                                </Link>
                                                            </Button>
                                                        )}
                                                        <DropdownMenu>
                                                            <DropdownMenuTrigger asChild>
                                                                <Button variant="ghost" size="sm">
                                                                    <MoreHorizontal className="h-4 w-4" />
                                                                </Button>
                                                            </DropdownMenuTrigger>
                                                            <DropdownMenuContent align="end">
                                                                <DropdownMenuItem asChild>
                                                                    <Link href={`/admin/orders/${order.id}`}>
                                                                        <Eye className="mr-2 h-4 w-4" />
                                                                        {t('admin.orders.view_details')}
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                                <DropdownMenuItem asChild>
                                                                    <Link href={`/admin/orders/${order.id}/edit`}>
                                                                        <Edit className="mr-2 h-4 w-4" />
                                                                        {t('admin.orders.edit_order')}
                                                                    </Link>
                                                                </DropdownMenuItem>
                                                                <DropdownMenuSeparator />
                                                                <DropdownMenuItem className="text-red-600">
                                                                    {t('admin.orders.cancel_order')}
                                                                </DropdownMenuItem>
                                                            </DropdownMenuContent>
                                                        </DropdownMenu>
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="py-8 text-center text-muted-foreground">
                                            {t('admin.orders.no_orders_found')}
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Mobile Card View */}
                    <div className="space-y-3 p-3 md:hidden">
                        {ordersData.length > 0 ? (
                            ordersData.map((order) => {
                                const customer = getCustomerInfo(order);
                                return (
                                    <Card key={order.id} className="p-4">
                                        <div className="mb-3 flex items-start justify-between">
                                            <div>
                                                <Link href={`/admin/orders/${order.id}`} className="font-medium text-primary hover:underline">
                                                    {order.order_number}
                                                </Link>
                                                <p className="text-sm text-muted-foreground">
                                                    {t('admin.orders.items_count', { count: order.orderItems?.length || 0 })}
                                                </p>
                                            </div>
                                            <div className="flex gap-2">
                                                <Badge className={statusColors[order.status as keyof typeof statusColors]} variant="secondary">
                                                    {t(`admin.orders.status.${order.status}`)}
                                                </Badge>
                                                {order.can_ship && (
                                                    <Button asChild size="sm" variant="outline">
                                                        <Link href={`/admin/orders/${order.id}/ship`}>
                                                            <Truck className="mr-1 h-4 w-4" />
                                                            {t('admin.orders.ship.button')}
                                                        </Link>
                                                    </Button>
                                                )}
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" size="sm">
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/orders/${order.id}`}>
                                                                <Eye className="mr-2 h-4 w-4" />
                                                                {t('admin.orders.view_details')}
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={`/admin/orders/${order.id}/edit`}>
                                                                <Edit className="mr-2 h-4 w-4" />
                                                                {t('admin.orders.edit_order')}
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem className="text-red-600">
                                                            {t('admin.orders.cancel_order')}
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">{t('admin.orders.label_customer')}</span>
                                                <div className="text-right">
                                                    <p className="text-sm font-medium">{customer.name}</p>
                                                    <p className="text-xs text-muted-foreground">{customer.email}</p>
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">{t('admin.orders.label_payment')}</span>
                                                <Badge
                                                    className={paymentStatusColors[order.payment_status as keyof typeof paymentStatusColors]}
                                                    variant="secondary"
                                                >
                                                    {t(`admin.orders.payment_status.${order.payment_status}`)}
                                                </Badge>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">{t('admin.orders.label_total')}</span>
                                                <span className="font-bold">{formatCurrency(order.total_amount)}</span>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">{t('admin.orders.label_date')}</span>
                                                <span className="text-sm">{formatDate(order.created_at)}</span>
                                            </div>
                                        </div>
                                    </Card>
                                );
                            })
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">{t('admin.orders.no_orders_found')}</div>
                        )}
                    </div>

                    {/* Mobile-First Pagination */}
                    {ordersMeta.last_page > 1 && (
                        <div className="border-t px-4 py-4">
                            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                                <p className="text-center text-sm text-muted-foreground md:text-left">
                                    {t('admin.orders.pagination_showing', {
                                        from: ordersMeta.from,
                                        to: ordersMeta.to,
                                        total: ordersMeta.total,
                                    })}
                                </p>
                                <div className="flex items-center justify-center space-x-1 md:space-x-2">
                                    {ordersLinks.map((link, index) => (
                                        <Button
                                            key={index}
                                            variant={link.active ? 'default' : 'outline'}
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
                <div className="fixed right-0 bottom-0 left-0 z-50 border-t bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/60 md:hidden">
                    <div className="safe-area-inset-bottom">
                        <div className="flex justify-center gap-2 p-3">
                            <Button variant="outline" size="sm" onClick={() => setIsKPIsOpen(!isKPIsOpen)} className="h-12 flex-1 text-xs">
                                <TrendingUp className="mr-1 h-4 w-4" />
                                {t('admin.orders.mobile_stats')}
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
                                className="h-12 flex-1 text-xs"
                            >
                                <Filter className="mr-1 h-4 w-4" />
                                {t('admin.orders.mobile_filters')}
                            </Button>
                            <Button variant="outline" size="sm" asChild className="h-12 flex-1 text-xs">
                                <Link href="/admin/dashboard">
                                    <Package className="mr-1 h-4 w-4" />
                                    {t('admin.orders.mobile_dashboard')}
                                </Link>
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

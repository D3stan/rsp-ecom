import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type SharedData, type Order } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
    ShoppingBag, 
    User, 
    CreditCard, 
    Heart, 
    Package, 
    TrendingUp,
    Clock,
    CheckCircle,
    Truck,
    Star
} from 'lucide-react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

interface DashboardProps {
    recentOrders: Order[];
    orderStats: {
        total: number;
        pending: number;
        completed: number;
    };
    wishlistCount: number;
}

export default function Dashboard({ recentOrders = [], orderStats = { total: 0, pending: 0, completed: 0 }, wishlistCount = 0 }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const user = auth.user!;

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const getOrderStatusIcon = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending':
                return <Clock className="h-4 w-4" />;
            case 'completed':
                return <CheckCircle className="h-4 w-4" />;
            case 'shipped':
                return <Truck className="h-4 w-4" />;
            default:
                return <Package className="h-4 w-4" />;
        }
    };

    const getOrderStatusColor = (status: string) => {
        switch (status.toLowerCase()) {
            case 'pending':
                return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'completed':
                return 'bg-green-100 text-green-800 border-green-200';
            case 'shipped':
                return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'cancelled':
                return 'bg-red-100 text-red-800 border-red-200';
            default:
                return 'bg-gray-100 text-gray-800 border-gray-200';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            
            <div className="space-y-6 p-4 md:p-6">
                {/* Welcome Section */}
                <div className="space-y-2">
                    <h1 className="text-2xl font-bold tracking-tight">Welcome back, {user.name}!</h1>
                    <p className="text-muted-foreground">
                        Here's an overview of your account and recent activity.
                    </p>
                </div>

                {/* Quick Stats */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Orders</CardTitle>
                            <ShoppingBag className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{orderStats.total}</div>
                            <p className="text-xs text-muted-foreground">
                                All time purchases
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Pending Orders</CardTitle>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{orderStats.pending}</div>
                            <p className="text-xs text-muted-foreground">
                                Awaiting processing
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Completed Orders</CardTitle>
                            <TrendingUp className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{orderStats.completed}</div>
                            <p className="text-xs text-muted-foreground">
                                Successfully delivered
                            </p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Wishlist Items</CardTitle>
                            <Heart className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{wishlistCount}</div>
                            <p className="text-xs text-muted-foreground">
                                Saved for later
                            </p>
                        </CardContent>
                    </Card>
                </div>

                {/* Quick Actions and Recent Orders Grid */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Recent Orders */}
                    <div className="lg:col-span-2">
                        <Card>
                            <CardHeader>
                                <CardTitle>Recent Orders</CardTitle>
                                <CardDescription>
                                    Your latest purchases and their status
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {recentOrders.length > 0 ? (
                                    <div className="space-y-4">
                                        {recentOrders.slice(0, 5).map((order) => (
                                            <div key={order.id} className="flex items-center justify-between p-4 border rounded-lg">
                                                <div className="flex items-center space-x-4">
                                                    <div className="flex-shrink-0">
                                                        {getOrderStatusIcon(order.status)}
                                                    </div>
                                                    <div className="min-w-0 flex-1">
                                                        <p className="text-sm font-medium text-gray-900">
                                                            Order #{order.id}
                                                        </p>
                                                        <p className="text-sm text-muted-foreground">
                                                            {formatDate(order.created_at)} • {order.order_items.length} items
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center space-x-3">
                                                    <Badge 
                                                        variant="outline" 
                                                        className={getOrderStatusColor(order.status)}
                                                    >
                                                        {order.status}
                                                    </Badge>
                                                    <div className="text-sm font-medium">
                                                        {formatCurrency(order.total)}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                        
                                        <div className="pt-4">
                                            <Button variant="outline" className="w-full" asChild>
                                                <Link href={route('orders.index')}>
                                                    View All Orders
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="text-center py-8">
                                        <ShoppingBag className="mx-auto h-12 w-12 text-muted-foreground" />
                                        <h3 className="mt-2 text-sm font-medium">No orders yet</h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            Start shopping to see your orders here.
                                        </p>
                                        <div className="mt-6">
                                            <Button asChild>
                                                <Link href={route('products')}>
                                                    Browse Products
                                                </Link>
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>

                    {/* Quick Actions */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle>Quick Actions</CardTitle>
                                <CardDescription>
                                    Manage your account and preferences
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href={route('profile.edit')}>
                                        <User className="mr-2 h-4 w-4" />
                                        Edit Profile
                                    </Link>
                                </Button>
                                
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href="#" onClick={(e) => e.preventDefault()}>
                                        <CreditCard className="mr-2 h-4 w-4" />
                                        Payment Methods
                                    </Link>
                                </Button>
                                
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href={route('wishlist.index')}>
                                        <Heart className="mr-2 h-4 w-4" />
                                        View Wishlist
                                    </Link>
                                </Button>
                                
                                <Button variant="outline" className="w-full justify-start" asChild>
                                    <Link href="#" onClick={(e) => e.preventDefault()}>
                                        <Star className="mr-2 h-4 w-4" />
                                        Write Review
                                    </Link>
                                </Button>
                            </CardContent>
                        </Card>

                        {/* Account Summary */}
                        <Card>
                            <CardHeader>
                                <CardTitle>Account Summary</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Member since</span>
                                    <span className="text-sm font-medium">
                                        {formatDate(user.created_at)}
                                    </span>
                                </div>
                                
                                <div className="flex items-center justify-between">
                                    <span className="text-sm text-muted-foreground">Email status</span>
                                    <Badge variant={user.email_verified_at ? "outline" : "destructive"}>
                                        {user.email_verified_at ? "Verified" : "Unverified"}
                                    </Badge>
                                </div>
                                
                                {user.google_id && (
                                    <div className="flex items-center justify-between">
                                        <span className="text-sm text-muted-foreground">Google account</span>
                                        <Badge variant="outline">Connected</Badge>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

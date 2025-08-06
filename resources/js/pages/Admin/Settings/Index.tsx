import { type BreadcrumbItem } from '@/types';
import { Head, useForm } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Switch } from '@/components/ui/switch';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import AppLayout from '@/layouts/app-layout';
import { 
    Settings, 
    CreditCard, 
    Truck, 
    Receipt, 
    Mail,
    Save,
    ChevronDown,
    ChevronUp
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { useToast } from '@/contexts/ToastContext';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Admin', href: '/admin/dashboard' },
    { title: 'Settings', href: '/admin/settings' },
];

interface SettingsData {
    // General Settings
    site_name: string;
    site_description: string;
    contact_email: string;
    contact_phone: string;
    company_address: string;
    default_currency: string;
    timezone: string;

    // Payment Settings
    stripe_enabled: boolean;
    stripe_public_key: string;
    stripe_secret_key: string;
    stripe_webhook_secret: string;
    paypal_enabled: boolean;
    paypal_client_id: string;
    paypal_client_secret: string;
    minimum_order_amount: number;

    // Shipping Settings
    shipping_enabled: boolean;
    free_shipping_threshold: number;
    default_shipping_cost: number;
    shipping_calculation_method: string;
    allow_international_shipping: boolean;
    processing_time_days: number;
    shipping_zones: any[];

    // Tax Settings
    tax_enabled: boolean;
    default_tax_rate: number;
    prices_include_tax: boolean;
    tax_calculation_method: string;
    collect_tax_for_digital_products: boolean;
    tax_number: string;

    // Email Settings
    email_notifications_enabled: boolean;
    order_confirmation_enabled: boolean;
    order_shipped_enabled: boolean;
    order_delivered_enabled: boolean;
    order_cancelled_enabled: boolean;
    newsletter_enabled: boolean;
    from_email: string;
    from_name: string;
    admin_notification_email: string;
}

interface Props {
    settings: SettingsData;
}

export default function AdminSettings({ settings }: Props) {
    const [activeTab, setActiveTab] = useState('general');
    const { addToast } = useToast();

    // Get active tab from URL hash
    useEffect(() => {
        const hash = window.location.hash.replace('#', '');
        if (hash && ['general', 'payment', 'shipping', 'tax', 'email'].includes(hash)) {
            setActiveTab(hash);
        }
    }, []);

    // Update URL hash when tab changes
    const handleTabChange = (value: string) => {
        setActiveTab(value);
        window.history.replaceState(null, '', `#${value}`);
    };

    // Form handlers for each section
    const generalForm = useForm({
        site_name: settings.site_name,
        site_description: settings.site_description,
        contact_email: settings.contact_email,
        contact_phone: settings.contact_phone,
        company_address: settings.company_address,
        default_currency: settings.default_currency,
        timezone: settings.timezone,
    });

    const paymentForm = useForm({
        stripe_enabled: settings.stripe_enabled,
        stripe_public_key: settings.stripe_public_key,
        stripe_secret_key: settings.stripe_secret_key,
        stripe_webhook_secret: settings.stripe_webhook_secret,
        paypal_enabled: settings.paypal_enabled,
        paypal_client_id: settings.paypal_client_id,
        paypal_client_secret: settings.paypal_client_secret,
        minimum_order_amount: settings.minimum_order_amount,
    });

    const shippingForm = useForm({
        shipping_enabled: settings.shipping_enabled,
        free_shipping_threshold: settings.free_shipping_threshold,
        default_shipping_cost: settings.default_shipping_cost,
        shipping_calculation_method: settings.shipping_calculation_method,
        allow_international_shipping: settings.allow_international_shipping,
        processing_time_days: settings.processing_time_days,
        shipping_zones: settings.shipping_zones,
    });

    const taxForm = useForm({
        tax_enabled: settings.tax_enabled,
        default_tax_rate: settings.default_tax_rate,
        prices_include_tax: settings.prices_include_tax,
        tax_calculation_method: settings.tax_calculation_method,
        collect_tax_for_digital_products: settings.collect_tax_for_digital_products,
        tax_number: settings.tax_number,
    });

    const emailForm = useForm({
        email_notifications_enabled: settings.email_notifications_enabled,
        order_confirmation_enabled: settings.order_confirmation_enabled,
        order_shipped_enabled: settings.order_shipped_enabled,
        order_delivered_enabled: settings.order_delivered_enabled,
        order_cancelled_enabled: settings.order_cancelled_enabled,
        newsletter_enabled: settings.newsletter_enabled,
        from_email: settings.from_email,
        from_name: settings.from_name,
        admin_notification_email: settings.admin_notification_email,
    });

    const submitGeneral: FormEventHandler = (e) => {
        e.preventDefault();
        generalForm.patch(route('admin.settings.update-general'), {
            preserveScroll: true,
            onSuccess: () => addToast({
                type: 'success',
                title: 'General settings updated successfully'
            }),
        });
    };

    const submitPayment: FormEventHandler = (e) => {
        e.preventDefault();
        paymentForm.patch(route('admin.settings.update-payment'), {
            preserveScroll: true,
            onSuccess: () => addToast({
                type: 'success',
                title: 'Payment settings updated successfully'
            }),
        });
    };

    const submitShipping: FormEventHandler = (e) => {
        e.preventDefault();
        shippingForm.patch(route('admin.settings.update-shipping'), {
            preserveScroll: true,
            onSuccess: () => addToast({
                type: 'success',
                title: 'Shipping settings updated successfully'
            }),
        });
    };

    const submitTax: FormEventHandler = (e) => {
        e.preventDefault();
        taxForm.patch(route('admin.settings.update-tax'), {
            preserveScroll: true,
            onSuccess: () => addToast({
                type: 'success',
                title: 'Tax settings updated successfully'
            }),
        });
    };

    const submitEmail: FormEventHandler = (e) => {
        e.preventDefault();
        emailForm.patch(route('admin.settings.update-email'), {
            preserveScroll: true,
            onSuccess: () => addToast({
                type: 'success',
                title: 'Email settings updated successfully'
            }),
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Settings" />

            <AdminSettingsLayout activeTab={activeTab}>
                <Tabs value={activeTab} onValueChange={handleTabChange} className="w-full">
                    <TabsList className="grid w-full grid-cols-5 mb-8">
                        <TabsTrigger value="general" className="flex items-center gap-2">
                            <Settings className="w-4 h-4" />
                            <span className="hidden sm:inline">General</span>
                        </TabsTrigger>
                        <TabsTrigger value="payment" className="flex items-center gap-2">
                            <CreditCard className="w-4 h-4" />
                            <span className="hidden sm:inline">Payment</span>
                        </TabsTrigger>
                        <TabsTrigger value="shipping" className="flex items-center gap-2">
                            <Truck className="w-4 h-4" />
                            <span className="hidden sm:inline">Shipping</span>
                        </TabsTrigger>
                        <TabsTrigger value="tax" className="flex items-center gap-2">
                            <Receipt className="w-4 h-4" />
                            <span className="hidden sm:inline">Tax</span>
                        </TabsTrigger>
                        <TabsTrigger value="email" className="flex items-center gap-2">
                            <Mail className="w-4 h-4" />
                            <span className="hidden sm:inline">Email</span>
                        </TabsTrigger>
                    </TabsList>

                    {/* General Settings */}
                    <TabsContent value="general">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Settings className="w-5 h-5" />
                                    General Settings
                                </CardTitle>
                                <CardDescription>
                                    Configure basic store information and global settings
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitGeneral} className="space-y-6">
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="site_name">Store Name</Label>
                                            <Input
                                                id="site_name"
                                                value={generalForm.data.site_name}
                                                onChange={(e) => generalForm.setData('site_name', e.target.value)}
                                                className={cn(generalForm.errors.site_name && 'border-red-500')}
                                            />
                                            {generalForm.errors.site_name && (
                                                <p className="text-sm text-red-500">{generalForm.errors.site_name}</p>
                                            )}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="contact_email">Contact Email</Label>
                                            <Input
                                                id="contact_email"
                                                type="email"
                                                value={generalForm.data.contact_email}
                                                onChange={(e) => generalForm.setData('contact_email', e.target.value)}
                                                className={cn(generalForm.errors.contact_email && 'border-red-500')}
                                            />
                                            {generalForm.errors.contact_email && (
                                                <p className="text-sm text-red-500">{generalForm.errors.contact_email}</p>
                                            )}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="site_description">Store Description</Label>
                                        <Textarea
                                            id="site_description"
                                            value={generalForm.data.site_description}
                                            onChange={(e) => generalForm.setData('site_description', e.target.value)}
                                            rows={3}
                                            className={cn(generalForm.errors.site_description && 'border-red-500')}
                                        />
                                        {generalForm.errors.site_description && (
                                            <p className="text-sm text-red-500">{generalForm.errors.site_description}</p>
                                        )}
                                    </div>

                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="contact_phone">Contact Phone</Label>
                                            <Input
                                                id="contact_phone"
                                                value={generalForm.data.contact_phone}
                                                onChange={(e) => generalForm.setData('contact_phone', e.target.value)}
                                                className={cn(generalForm.errors.contact_phone && 'border-red-500')}
                                            />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="default_currency">Default Currency</Label>
                                            <Select
                                                value={generalForm.data.default_currency}
                                                onValueChange={(value) => generalForm.setData('default_currency', value)}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="EUR">EUR (€)</SelectItem>
                                                    <SelectItem value="USD">USD ($)</SelectItem>
                                                    <SelectItem value="GBP">GBP (£)</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="company_address">Company Address</Label>
                                        <Textarea
                                            id="company_address"
                                            value={generalForm.data.company_address}
                                            onChange={(e) => generalForm.setData('company_address', e.target.value)}
                                            rows={3}
                                        />
                                    </div>

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={generalForm.processing}>
                                            <Save className="w-4 h-4 mr-2" />
                                            Save General Settings
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Payment Settings */}
                    <TabsContent value="payment">
                        <div className="space-y-6">
                            {/* Stripe Settings */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <CreditCard className="w-5 h-5" />
                                        Stripe Payment Gateway
                                    </CardTitle>
                                    <CardDescription>
                                        Configure Stripe for credit card payments
                                    </CardDescription>
                                </CardHeader>
                                <CardContent>
                                    <form onSubmit={submitPayment} className="space-y-6">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-0.5">
                                                <Label htmlFor="stripe_enabled">Enable Stripe</Label>
                                                <p className="text-sm text-muted-foreground">
                                                    Allow customers to pay with credit cards via Stripe
                                                </p>
                                            </div>
                                            <Switch
                                                id="stripe_enabled"
                                                checked={paymentForm.data.stripe_enabled}
                                                onCheckedChange={(checked) => paymentForm.setData('stripe_enabled', checked)}
                                            />
                                        </div>

                                        {paymentForm.data.stripe_enabled && (
                                            <>
                                                <Separator />
                                                <div className="grid gap-4 md:grid-cols-2">
                                                    <div className="space-y-2">
                                                        <Label htmlFor="stripe_public_key">Stripe Public Key</Label>
                                                        <Input
                                                            id="stripe_public_key"
                                                            value={paymentForm.data.stripe_public_key}
                                                            onChange={(e) => paymentForm.setData('stripe_public_key', e.target.value)}
                                                            placeholder="pk_..."
                                                        />
                                                    </div>

                                                    <div className="space-y-2">
                                                        <Label htmlFor="stripe_secret_key">Stripe Secret Key</Label>
                                                        <Input
                                                            id="stripe_secret_key"
                                                            type="password"
                                                            value={paymentForm.data.stripe_secret_key}
                                                            onChange={(e) => paymentForm.setData('stripe_secret_key', e.target.value)}
                                                            placeholder="sk_..."
                                                        />
                                                    </div>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="stripe_webhook_secret">Stripe Webhook Secret</Label>
                                                    <Input
                                                        id="stripe_webhook_secret"
                                                        type="password"
                                                        value={paymentForm.data.stripe_webhook_secret}
                                                        onChange={(e) => paymentForm.setData('stripe_webhook_secret', e.target.value)}
                                                        placeholder="whsec_..."
                                                    />
                                                    <p className="text-sm text-muted-foreground">
                                                        Required for webhook signature verification
                                                    </p>
                                                </div>
                                            </>
                                        )}

                                        <Separator />

                                        <div className="space-y-2">
                                            <Label htmlFor="minimum_order_amount">Minimum Order Amount</Label>
                                            <Input
                                                id="minimum_order_amount"
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                value={paymentForm.data.minimum_order_amount}
                                                onChange={(e) => paymentForm.setData('minimum_order_amount', parseFloat(e.target.value) || 0)}
                                            />
                                            <p className="text-sm text-muted-foreground">
                                                Set to 0 to disable minimum order requirement
                                            </p>
                                        </div>

                                        <div className="flex justify-end">
                                            <Button type="submit" disabled={paymentForm.processing}>
                                                <Save className="w-4 h-4 mr-2" />
                                                Save Payment Settings
                                            </Button>
                                        </div>
                                    </form>
                                </CardContent>
                            </Card>
                        </div>
                    </TabsContent>

                    {/* Shipping Settings */}
                    <TabsContent value="shipping">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Truck className="w-5 h-5" />
                                    Shipping Configuration
                                </CardTitle>
                                <CardDescription>
                                    Configure shipping options and costs
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitShipping} className="space-y-6">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="shipping_enabled">Enable Shipping</Label>
                                            <p className="text-sm text-muted-foreground">
                                                Allow customers to have products shipped
                                            </p>
                                        </div>
                                        <Switch
                                            id="shipping_enabled"
                                            checked={shippingForm.data.shipping_enabled}
                                            onCheckedChange={(checked) => shippingForm.setData('shipping_enabled', checked)}
                                        />
                                    </div>

                                    {shippingForm.data.shipping_enabled && (
                                        <>
                                            <Separator />
                                            
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="default_shipping_cost">Default Shipping Cost</Label>
                                                    <Input
                                                        id="default_shipping_cost"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={shippingForm.data.default_shipping_cost}
                                                        onChange={(e) => shippingForm.setData('default_shipping_cost', parseFloat(e.target.value) || 0)}
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="free_shipping_threshold">Free Shipping Threshold</Label>
                                                    <Input
                                                        id="free_shipping_threshold"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        value={shippingForm.data.free_shipping_threshold}
                                                        onChange={(e) => shippingForm.setData('free_shipping_threshold', parseFloat(e.target.value) || 0)}
                                                    />
                                                    <p className="text-sm text-muted-foreground">
                                                        Set to 0 to disable free shipping
                                                    </p>
                                                </div>
                                            </div>

                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="shipping_calculation_method">Calculation Method</Label>
                                                    <Select
                                                        value={shippingForm.data.shipping_calculation_method}
                                                        onValueChange={(value) => shippingForm.setData('shipping_calculation_method', value)}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="flat_rate">Flat Rate</SelectItem>
                                                            <SelectItem value="weight_based">Weight Based</SelectItem>
                                                            <SelectItem value="zone_based">Zone Based</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="processing_time_days">Processing Time (Days)</Label>
                                                    <Input
                                                        id="processing_time_days"
                                                        type="number"
                                                        min="1"
                                                        max="30"
                                                        value={shippingForm.data.processing_time_days}
                                                        onChange={(e) => shippingForm.setData('processing_time_days', parseInt(e.target.value) || 1)}
                                                    />
                                                </div>
                                            </div>

                                            <div className="flex items-center justify-between">
                                                <div className="space-y-0.5">
                                                    <Label htmlFor="allow_international_shipping">International Shipping</Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        Allow shipping to international destinations
                                                    </p>
                                                </div>
                                                <Switch
                                                    id="allow_international_shipping"
                                                    checked={shippingForm.data.allow_international_shipping}
                                                    onCheckedChange={(checked) => shippingForm.setData('allow_international_shipping', checked)}
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={shippingForm.processing}>
                                            <Save className="w-4 h-4 mr-2" />
                                            Save Shipping Settings
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Tax Settings */}
                    <TabsContent value="tax">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Receipt className="w-5 h-5" />
                                    Tax Configuration
                                </CardTitle>
                                <CardDescription>
                                    Configure tax rates and calculation methods
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitTax} className="space-y-6">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="tax_enabled">Enable Tax Calculation</Label>
                                            <p className="text-sm text-muted-foreground">
                                                Calculate and collect taxes on orders
                                            </p>
                                        </div>
                                        <Switch
                                            id="tax_enabled"
                                            checked={taxForm.data.tax_enabled}
                                            onCheckedChange={(checked) => taxForm.setData('tax_enabled', checked)}
                                        />
                                    </div>

                                    {taxForm.data.tax_enabled && (
                                        <>
                                            <Separator />
                                            
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="default_tax_rate">Default Tax Rate (%)</Label>
                                                    <Input
                                                        id="default_tax_rate"
                                                        type="number"
                                                        step="0.01"
                                                        min="0"
                                                        max="100"
                                                        value={taxForm.data.default_tax_rate}
                                                        onChange={(e) => taxForm.setData('default_tax_rate', parseFloat(e.target.value) || 0)}
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="tax_calculation_method">Calculation Based On</Label>
                                                    <Select
                                                        value={taxForm.data.tax_calculation_method}
                                                        onValueChange={(value) => taxForm.setData('tax_calculation_method', value)}
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            <SelectItem value="destination">Customer Address</SelectItem>
                                                            <SelectItem value="origin">Store Address</SelectItem>
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="tax_number">Tax Number / VAT ID</Label>
                                                <Input
                                                    id="tax_number"
                                                    value={taxForm.data.tax_number}
                                                    onChange={(e) => taxForm.setData('tax_number', e.target.value)}
                                                    placeholder="IT12345678901"
                                                />
                                            </div>

                                            <div className="space-y-4">
                                                <div className="flex items-center justify-between">
                                                    <div className="space-y-0.5">
                                                        <Label htmlFor="prices_include_tax">Prices Include Tax</Label>
                                                        <p className="text-sm text-muted-foreground">
                                                            Product prices already include tax
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        id="prices_include_tax"
                                                        checked={taxForm.data.prices_include_tax}
                                                        onCheckedChange={(checked) => taxForm.setData('prices_include_tax', checked)}
                                                    />
                                                </div>

                                                <div className="flex items-center justify-between">
                                                    <div className="space-y-0.5">
                                                        <Label htmlFor="collect_tax_for_digital_products">Tax Digital Products</Label>
                                                        <p className="text-sm text-muted-foreground">
                                                            Collect tax on digital products and services
                                                        </p>
                                                    </div>
                                                    <Switch
                                                        id="collect_tax_for_digital_products"
                                                        checked={taxForm.data.collect_tax_for_digital_products}
                                                        onCheckedChange={(checked) => taxForm.setData('collect_tax_for_digital_products', checked)}
                                                    />
                                                </div>
                                            </div>
                                        </>
                                    )}

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={taxForm.processing}>
                                            <Save className="w-4 h-4 mr-2" />
                                            Save Tax Settings
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>

                    {/* Email Settings */}
                    <TabsContent value="email">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Mail className="w-5 h-5" />
                                    Email Configuration
                                </CardTitle>
                                <CardDescription>
                                    Configure email notifications and settings
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                <form onSubmit={submitEmail} className="space-y-6">
                                    <div className="flex items-center justify-between">
                                        <div className="space-y-0.5">
                                            <Label htmlFor="email_notifications_enabled">Enable Email Notifications</Label>
                                            <p className="text-sm text-muted-foreground">
                                                Send automated email notifications to customers
                                            </p>
                                        </div>
                                        <Switch
                                            id="email_notifications_enabled"
                                            checked={emailForm.data.email_notifications_enabled}
                                            onCheckedChange={(checked) => emailForm.setData('email_notifications_enabled', checked)}
                                        />
                                    </div>

                                    {emailForm.data.email_notifications_enabled && (
                                        <>
                                            <Separator />
                                            
                                            <div className="grid gap-4 md:grid-cols-2">
                                                <div className="space-y-2">
                                                    <Label htmlFor="from_email">From Email Address</Label>
                                                    <Input
                                                        id="from_email"
                                                        type="email"
                                                        value={emailForm.data.from_email}
                                                        onChange={(e) => emailForm.setData('from_email', e.target.value)}
                                                    />
                                                </div>

                                                <div className="space-y-2">
                                                    <Label htmlFor="from_name">From Name</Label>
                                                    <Input
                                                        id="from_name"
                                                        value={emailForm.data.from_name}
                                                        onChange={(e) => emailForm.setData('from_name', e.target.value)}
                                                    />
                                                </div>
                                            </div>

                                            <div className="space-y-2">
                                                <Label htmlFor="admin_notification_email">Admin Notification Email</Label>
                                                <Input
                                                    id="admin_notification_email"
                                                    type="email"
                                                    value={emailForm.data.admin_notification_email}
                                                    onChange={(e) => emailForm.setData('admin_notification_email', e.target.value)}
                                                    placeholder="admin@yourstore.com"
                                                />
                                                <p className="text-sm text-muted-foreground">
                                                    Email address to receive order notifications
                                                </p>
                                            </div>

                                            <Separator />

                                            <div className="space-y-4">
                                                <h4 className="text-sm font-medium">Customer Email Notifications</h4>
                                                
                                                <div className="space-y-3">
                                                    <div className="flex items-center justify-between">
                                                        <div className="space-y-0.5">
                                                            <Label htmlFor="order_confirmation_enabled">Order Confirmation</Label>
                                                            <p className="text-sm text-muted-foreground">
                                                                Send confirmation when order is placed
                                                            </p>
                                                        </div>
                                                        <Switch
                                                            id="order_confirmation_enabled"
                                                            checked={emailForm.data.order_confirmation_enabled}
                                                            onCheckedChange={(checked) => emailForm.setData('order_confirmation_enabled', checked)}
                                                        />
                                                    </div>

                                                    <div className="flex items-center justify-between">
                                                        <div className="space-y-0.5">
                                                            <Label htmlFor="order_shipped_enabled">Order Shipped</Label>
                                                            <p className="text-sm text-muted-foreground">
                                                                Send notification when order is shipped
                                                            </p>
                                                        </div>
                                                        <Switch
                                                            id="order_shipped_enabled"
                                                            checked={emailForm.data.order_shipped_enabled}
                                                            onCheckedChange={(checked) => emailForm.setData('order_shipped_enabled', checked)}
                                                        />
                                                    </div>

                                                    <div className="flex items-center justify-between">
                                                        <div className="space-y-0.5">
                                                            <Label htmlFor="order_delivered_enabled">Order Delivered</Label>
                                                            <p className="text-sm text-muted-foreground">
                                                                Send notification when order is delivered
                                                            </p>
                                                        </div>
                                                        <Switch
                                                            id="order_delivered_enabled"
                                                            checked={emailForm.data.order_delivered_enabled}
                                                            onCheckedChange={(checked) => emailForm.setData('order_delivered_enabled', checked)}
                                                        />
                                                    </div>

                                                    <div className="flex items-center justify-between">
                                                        <div className="space-y-0.5">
                                                            <Label htmlFor="order_cancelled_enabled">Order Cancelled</Label>
                                                            <p className="text-sm text-muted-foreground">
                                                                Send notification when order is cancelled
                                                            </p>
                                                        </div>
                                                        <Switch
                                                            id="order_cancelled_enabled"
                                                            checked={emailForm.data.order_cancelled_enabled}
                                                            onCheckedChange={(checked) => emailForm.setData('order_cancelled_enabled', checked)}
                                                        />
                                                    </div>
                                                </div>
                                            </div>

                                            <Separator />

                                            <div className="flex items-center justify-between">
                                                <div className="space-y-0.5">
                                                    <Label htmlFor="newsletter_enabled">Newsletter Subscriptions</Label>
                                                    <p className="text-sm text-muted-foreground">
                                                        Allow customers to subscribe to marketing emails
                                                    </p>
                                                </div>
                                                <Switch
                                                    id="newsletter_enabled"
                                                    checked={emailForm.data.newsletter_enabled}
                                                    onCheckedChange={(checked) => emailForm.setData('newsletter_enabled', checked)}
                                                />
                                            </div>
                                        </>
                                    )}

                                    <div className="flex justify-end">
                                        <Button type="submit" disabled={emailForm.processing}>
                                            <Save className="w-4 h-4 mr-2" />
                                            Save Email Settings
                                        </Button>
                                    </div>
                                </form>
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </AdminSettingsLayout>
        </AppLayout>
    );
}

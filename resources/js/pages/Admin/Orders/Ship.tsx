import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    ChevronDown,
    ChevronUp,
    Package,
    User,
    MapPin,
    Box,
    Shield,
    CreditCard,
    ArrowLeft,
} from 'lucide-react';

interface Order {
    id: number;
    order_number: string;
    user?: {
        id: number;
        name: string;
        email: string;
        phone?: string;
    };
    guest_email?: string;
    guest_phone?: string;
    status: string;
    total_amount: number;
    shippingAddress?: {
        first_name: string;
        last_name: string;
        company?: string;
        address_line_1: string;
        address_line_2?: string;
        city: string;
        state: string;
        postal_code: string;
        country: string;
        phone?: string;
    };
    orderItems?: Array<{
        id: number;
        product: {
            name: string;
        };
        quantity: number;
        price: number;
    }>;
}

interface AdminSettings {
    company_name?: string;
    company_address?: string;
    company_phone?: string;
    company_email?: string;
    iban?: string;
    account_holder?: string;
}

interface Props {
    order: Order;
    adminSettings: AdminSettings;
}

export default function OrderShip({ order, adminSettings }: Props) {
    const [openSections, setOpenSections] = useState({
        sender: false,
        customer: true,
        shipping: true,
        box: true,
        additional: true,
    });

    // Form state
    const [senderInfo, setSenderInfo] = useState({
        company_name: adminSettings.company_name || '',
        address: adminSettings.company_address || '',
        phone: adminSettings.company_phone || '',
        email: adminSettings.company_email || '',
    });

    const [boxInfo, setBoxInfo] = useState({
        weight: '',
        length: '',
        width: '',
        height: '',
        tracking_number: '',
        carrier: '',
        service_type: '',
    });

    const [additionalSettings, setAdditionalSettings] = useState({
        insurance_value: '',
        contrassegno_enabled: false,
        contrassegno_amount: '',
        iban: adminSettings.iban || '',
        account_holder: adminSettings.account_holder || '',
        notes: '',
    });

    const [isSubmitting, setIsSubmitting] = useState(false);

    const toggleSection = (section: keyof typeof openSections) => {
        setOpenSections(prev => ({
            ...prev,
            [section]: !prev[section]
        }));
    };

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setIsSubmitting(true);

        try {
            const shippingData = {
                sender_info: senderInfo,
                box_info: boxInfo,
                additional_settings: additionalSettings,
            };

            router.post(`/admin/orders/${order.id}/ship`, shippingData, {
                onSuccess: () => {
                    console.log('Order shipped successfully!');
                },
                onError: (errors) => {
                    console.error('Failed to process shipping', errors);
                },
                onFinish: () => {
                    setIsSubmitting(false);
                }
            });
        } catch (error) {
            console.error('An error occurred while processing shipping', error);
            setIsSubmitting(false);
        }
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const getCustomerInfo = () => {
        if (order.user) {
            return {
                name: order.user.name,
                email: order.user.email,
                phone: order.user.phone || 'N/A',
            };
        }
        return {
            name: 'Guest Customer',
            email: order.guest_email || 'N/A',
            phone: order.guest_phone || 'N/A',
        };
    };

    const customer = getCustomerInfo();

    return (
        <AppLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Orders', href: '/admin/orders' },
                { title: `Order ${order.order_number}`, href: `/admin/orders/${order.id}` },
                { title: 'Ship Order', href: '#' },
            ]}
        >
            <Head title={`Ship Order ${order.order_number} - Admin`} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-3 mb-2">
                            <Button variant="outline" size="sm" asChild>
                                <Link href={`/admin/orders/${order.id}`}>
                                    <ArrowLeft className="h-4 w-4 mr-1" />
                                    Back to Order
                                </Link>
                            </Button>
                        </div>
                        <h1 className="text-2xl font-bold">Ship Order {order.order_number}</h1>
                        <p className="text-muted-foreground">
                            Prepare shipping details for this order
                        </p>
                    </div>
                    <Badge variant="secondary" className="bg-yellow-100 text-yellow-800">
                        {order.status}
                    </Badge>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* 1. Sender Shipping Information (Collapsed by default) */}
                    <Card>
                        <Collapsible
                            open={openSections.sender}
                            onOpenChange={() => toggleSection('sender')}
                        >
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Package className="h-5 w-5" />
                                            <CardTitle>Sender Shipping Information</CardTitle>
                                        </div>
                                        {openSections.sender ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4">
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="company_name">Company Name</Label>
                                            <Input
                                                id="company_name"
                                                value={senderInfo.company_name}
                                                onChange={(e) => setSenderInfo(prev => ({
                                                    ...prev,
                                                    company_name: e.target.value
                                                }))}
                                                placeholder="Your company name"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="sender_email">Email</Label>
                                            <Input
                                                id="sender_email"
                                                type="email"
                                                value={senderInfo.email}
                                                onChange={(e) => setSenderInfo(prev => ({
                                                    ...prev,
                                                    email: e.target.value
                                                }))}
                                                placeholder="company@example.com"
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="sender_address">Address</Label>
                                        <Textarea
                                            id="sender_address"
                                            value={senderInfo.address}
                                            onChange={(e) => setSenderInfo(prev => ({
                                                ...prev,
                                                address: e.target.value
                                            }))}
                                            placeholder="Complete company address"
                                            rows={3}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="sender_phone">Phone</Label>
                                        <Input
                                            id="sender_phone"
                                            value={senderInfo.phone}
                                            onChange={(e) => setSenderInfo(prev => ({
                                                ...prev,
                                                phone: e.target.value
                                            }))}
                                            placeholder="+39 123 456 7890"
                                        />
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    {/* 2. Customer Information (Auto-filled, expanded by default) */}
                    <Card>
                        <Collapsible
                            open={openSections.customer}
                            onOpenChange={() => toggleSection('customer')}
                        >
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <User className="h-5 w-5" />
                                            <CardTitle>Customer Information</CardTitle>
                                        </div>
                                        {openSections.customer ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4">
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <Label>Customer Name</Label>
                                            <Input value={customer.name} readOnly className="bg-muted" />
                                        </div>
                                        <div>
                                            <Label>Email</Label>
                                            <Input value={customer.email} readOnly className="bg-muted" />
                                        </div>
                                    </div>
                                    <div>
                                        <Label>Phone</Label>
                                        <Input value={customer.phone} readOnly className="bg-muted" />
                                    </div>
                                    {order.orderItems && order.orderItems.length > 0 && (
                                        <div>
                                            <Label>Order Items</Label>
                                            <div className="mt-2 space-y-2">
                                                {order.orderItems.map((item) => (
                                                    <div key={item.id} className="flex justify-between items-center p-2 bg-muted rounded">
                                                        <span>{item.product.name}</span>
                                                        <span>Qty: {item.quantity} - {formatCurrency(item.price)}</span>
                                                    </div>
                                                ))}
                                                <div className="flex justify-between items-center p-2 bg-primary/10 rounded font-semibold">
                                                    <span>Total Order Value</span>
                                                    <span>{formatCurrency(order.total_amount)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    {/* 3. Shipping Address Information (Auto-filled, expanded by default) */}
                    <Card>
                        <Collapsible
                            open={openSections.shipping}
                            onOpenChange={() => toggleSection('shipping')}
                        >
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-5 w-5" />
                                            <CardTitle>Shipping Address Information</CardTitle>
                                        </div>
                                        {openSections.shipping ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4">
                                    {order.shippingAddress ? (
                                        <>
                                            <div className="grid md:grid-cols-2 gap-4">
                                                <div>
                                                    <Label>First Name</Label>
                                                    <Input value={order.shippingAddress.first_name} readOnly className="bg-muted" />
                                                </div>
                                                <div>
                                                    <Label>Last Name</Label>
                                                    <Input value={order.shippingAddress.last_name} readOnly className="bg-muted" />
                                                </div>
                                            </div>
                                            {order.shippingAddress.company && (
                                                <div>
                                                    <Label>Company</Label>
                                                    <Input value={order.shippingAddress.company} readOnly className="bg-muted" />
                                                </div>
                                            )}
                                            <div>
                                                <Label>Address Line 1</Label>
                                                <Input value={order.shippingAddress.address_line_1} readOnly className="bg-muted" />
                                            </div>
                                            {order.shippingAddress.address_line_2 && (
                                                <div>
                                                    <Label>Address Line 2</Label>
                                                    <Input value={order.shippingAddress.address_line_2} readOnly className="bg-muted" />
                                                </div>
                                            )}
                                            <div className="grid md:grid-cols-3 gap-4">
                                                <div>
                                                    <Label>City</Label>
                                                    <Input value={order.shippingAddress.city} readOnly className="bg-muted" />
                                                </div>
                                                <div>
                                                    <Label>State/Province</Label>
                                                    <Input value={order.shippingAddress.state} readOnly className="bg-muted" />
                                                </div>
                                                <div>
                                                    <Label>Postal Code</Label>
                                                    <Input value={order.shippingAddress.postal_code} readOnly className="bg-muted" />
                                                </div>
                                            </div>
                                            <div className="grid md:grid-cols-2 gap-4">
                                                <div>
                                                    <Label>Country</Label>
                                                    <Input value={order.shippingAddress.country} readOnly className="bg-muted" />
                                                </div>
                                                {order.shippingAddress.phone && (
                                                    <div>
                                                        <Label>Phone</Label>
                                                        <Input value={order.shippingAddress.phone} readOnly className="bg-muted" />
                                                    </div>
                                                )}
                                            </div>
                                        </>
                                    ) : (
                                        <p className="text-muted-foreground">No shipping address available</p>
                                    )}
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    {/* 4. Shipping Box Information */}
                    <Card>
                        <Collapsible
                            open={openSections.box}
                            onOpenChange={() => toggleSection('box')}
                        >
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Box className="h-5 w-5" />
                                            <CardTitle>Shipping Box Information</CardTitle>
                                        </div>
                                        {openSections.box ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4">
                                    <div className="grid md:grid-cols-4 gap-4">
                                        <div>
                                            <Label htmlFor="weight">Weight (kg)</Label>
                                            <Input
                                                id="weight"
                                                type="number"
                                                step="0.1"
                                                value={boxInfo.weight}
                                                onChange={(e) => setBoxInfo(prev => ({
                                                    ...prev,
                                                    weight: e.target.value
                                                }))}
                                                placeholder="1.5"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="length">Length (cm)</Label>
                                            <Input
                                                id="length"
                                                type="number"
                                                value={boxInfo.length}
                                                onChange={(e) => setBoxInfo(prev => ({
                                                    ...prev,
                                                    length: e.target.value
                                                }))}
                                                placeholder="30"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="width">Width (cm)</Label>
                                            <Input
                                                id="width"
                                                type="number"
                                                value={boxInfo.width}
                                                onChange={(e) => setBoxInfo(prev => ({
                                                    ...prev,
                                                    width: e.target.value
                                                }))}
                                                placeholder="20"
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="height">Height (cm)</Label>
                                            <Input
                                                id="height"
                                                type="number"
                                                value={boxInfo.height}
                                                onChange={(e) => setBoxInfo(prev => ({
                                                    ...prev,
                                                    height: e.target.value
                                                }))}
                                                placeholder="10"
                                            />
                                        </div>
                                    </div>
                                    <div className="grid md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="carrier">Carrier</Label>
                                            <Input
                                                id="carrier"
                                                value={boxInfo.carrier}
                                                onChange={(e) => setBoxInfo(prev => ({
                                                    ...prev,
                                                    carrier: e.target.value
                                                }))}
                                                placeholder="DHL, UPS, FedEx, etc."
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="service_type">Service Type</Label>
                                            <Input
                                                id="service_type"
                                                value={boxInfo.service_type}
                                                onChange={(e) => setBoxInfo(prev => ({
                                                    ...prev,
                                                    service_type: e.target.value
                                                }))}
                                                placeholder="Express, Standard, etc."
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="tracking_number">Tracking Number</Label>
                                        <Input
                                            id="tracking_number"
                                            value={boxInfo.tracking_number}
                                            onChange={(e) => setBoxInfo(prev => ({
                                                ...prev,
                                                tracking_number: e.target.value
                                            }))}
                                            placeholder="1234567890"
                                        />
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    {/* 5. Additional Settings */}
                    <Card>
                        <Collapsible
                            open={openSections.additional}
                            onOpenChange={() => toggleSection('additional')}
                        >
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-muted/50 transition-colors">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Shield className="h-5 w-5" />
                                            <CardTitle>Additional Settings</CardTitle>
                                        </div>
                                        {openSections.additional ? (
                                            <ChevronUp className="h-4 w-4" />
                                        ) : (
                                            <ChevronDown className="h-4 w-4" />
                                        )}
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-6">
                                    {/* Insurance */}
                                    <div>
                                        <Label htmlFor="insurance_value">Insurance Value (€)</Label>
                                        <Input
                                            id="insurance_value"
                                            type="number"
                                            step="0.01"
                                            value={additionalSettings.insurance_value}
                                            onChange={(e) => setAdditionalSettings(prev => ({
                                                ...prev,
                                                insurance_value: e.target.value
                                            }))}
                                            placeholder="0.00"
                                        />
                                    </div>

                                    {/* Contrassegno Section */}
                                    <div className="space-y-4">
                                        <div className="flex items-center space-x-2">
                                            <input
                                                type="checkbox"
                                                id="contrassegno_enabled"
                                                checked={additionalSettings.contrassegno_enabled}
                                                onChange={(e) => setAdditionalSettings(prev => ({
                                                    ...prev,
                                                    contrassegno_enabled: e.target.checked
                                                }))}
                                                className="rounded border-gray-300"
                                            />
                                            <Label htmlFor="contrassegno_enabled" className="flex items-center gap-2">
                                                <CreditCard className="h-4 w-4" />
                                                Enable Contrassegno (Cash on Delivery)
                                            </Label>
                                        </div>

                                        {additionalSettings.contrassegno_enabled && (
                                            <div className="space-y-4 ml-6 p-4 border rounded-lg bg-muted/50">
                                                <div>
                                                    <Label htmlFor="contrassegno_amount">Contrassegno Amount (€)</Label>
                                                    <Input
                                                        id="contrassegno_amount"
                                                        type="number"
                                                        step="0.01"
                                                        value={additionalSettings.contrassegno_amount}
                                                        onChange={(e) => setAdditionalSettings(prev => ({
                                                            ...prev,
                                                            contrassegno_amount: e.target.value
                                                        }))}
                                                        placeholder="0.00"
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="iban">IBAN</Label>
                                                    <Input
                                                        id="iban"
                                                        value={additionalSettings.iban}
                                                        onChange={(e) => setAdditionalSettings(prev => ({
                                                            ...prev,
                                                            iban: e.target.value
                                                        }))}
                                                        placeholder="IT60 X054 2811 1010 0000 0123 456"
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="account_holder">Account Holder Name</Label>
                                                    <Input
                                                        id="account_holder"
                                                        value={additionalSettings.account_holder}
                                                        onChange={(e) => setAdditionalSettings(prev => ({
                                                            ...prev,
                                                            account_holder: e.target.value
                                                        }))}
                                                        placeholder="Company Name or Person Name"
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Notes */}
                                    <div>
                                        <Label htmlFor="notes">Additional Notes</Label>
                                        <Textarea
                                            id="notes"
                                            value={additionalSettings.notes}
                                            onChange={(e) => setAdditionalSettings(prev => ({
                                                ...prev,
                                                notes: e.target.value
                                            }))}
                                            placeholder="Any additional shipping instructions or notes..."
                                            rows={3}
                                        />
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Collapsible>
                    </Card>

                    {/* Submit Button */}
                    <div className="flex justify-end space-x-4">
                        <Button type="button" variant="outline" asChild>
                            <Link href={`/admin/orders/${order.id}`}>
                                Cancel
                            </Link>
                        </Button>
                        <Button type="submit" disabled={isSubmitting}>
                            {isSubmitting ? 'Processing...' : 'Ship Order'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

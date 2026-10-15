import { forwardTransitions } from '@/components/admin/orders/OrderStatusActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import { useToast } from '@/contexts/ToastContext';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import { Head, router } from '@inertiajs/react';
import { ChevronDown, DollarSign, LoaderCircle, MapPin, Package, Save, User } from 'lucide-react';
import { useState } from 'react';

interface Product {
    id: number;
    name: string;
    price: number;
    image_url?: string;
}

interface OrderItem {
    id: number;
    product_id: number;
    quantity: number;
    price: number;
    total: number;
    product: Product;
}

interface User {
    id: number;
    name: string;
    email: string;
}

interface Address {
    id: number;
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
}

interface Order {
    id: number;
    order_number: string;
    status: string;
    payment_status: string;
    subtotal: number;
    shipping_amount: number;
    tax_amount: number;
    // VAT rate stored with the order: when set, tax_amount is already part of the prices and of the total
    tax_rate?: number | null;
    discount_amount?: number;
    total_amount: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    user: User;
    order_items: OrderItem[];
    billing_address?: Address;
    shipping_address?: Address;
    tracking_number?: string;
    tracking_carrier?: string | null;
    shipping_method?: string;
    estimated_delivery_date?: string;
}

interface FormData {
    status: string;
    shipping_amount: number;
    notes: string;
    order_items: OrderItem[];
    billing_address: Address | null;
    shipping_address: Address | null;
    tracking_number: string;
    tracking_carrier: string;
}

interface Carrier {
    code: string;
    name: string;
}

interface Props {
    order: Order;
    // statuses this order may move to next (server-side order lifecycle)
    allowed_transitions?: string[];
    // paid order, or payment in flight: lines, prices and amounts are read-only (money goes back through a refund)
    financials_locked?: boolean;
    carriers?: Carrier[];
}

// Radix Select cannot hold an empty value: this stands for "other / unknown" (sent as no carrier)
const NO_CARRIER = '__none__';

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

export default function Edit({ order, allowed_transitions = [], financials_locked = false, carriers = [] }: Props) {
    const { addToast } = useToast();
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const [validationErrors, setValidationErrors] = useState<string[]>([]);
    const [formData, setFormData] = useState<FormData>({
        status: order.status,
        shipping_amount: Number(order.shipping_amount),
        notes: order.notes || '',
        order_items: order.order_items,
        billing_address: order.billing_address || null,
        shipping_address: order.shipping_address || null,
        tracking_number: order.tracking_number || '',
        tracking_carrier: order.tracking_carrier || '',
    });

    const [openSections, setOpenSections] = useState({
        orderDetails: true,
        orderItems: true,
        financial: true,
        addresses: true,
    });

    const [showShipConfirmation, setShowShipConfirmation] = useState(false);

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const toggleSection = (section: keyof typeof openSections) => {
        setOpenSections((prev) => ({ ...prev, [section]: !prev[section] }));
    };

    const updateOrderItemQuantity = (itemId: number, newQuantity: number) => {
        if (newQuantity < 1) return;

        setFormData((prev) => ({
            ...prev,
            order_items: prev.order_items.map((item) =>
                item.id === itemId
                    ? {
                          ...item,
                          quantity: newQuantity,
                          total: item.price * newQuantity,
                      }
                    : item,
            ),
        }));

        // Clear validation errors when user fixes quantity issues
        clearValidationErrors();
    };

    // Amounts as the server stores them after saving (OrderFinancialEdit): derived from the lines, shipping and discount.
    // A paid order keeps its stored amounts.
    const preview = (() => {
        if (financials_locked) {
            return { subtotal: Number(order.subtotal), tax: Number(order.tax_amount), total: Number(order.total_amount) };
        }

        const cents = (value: number | string | undefined) => Math.round(Number(value ?? 0) * 100);
        const subtotal = formData.order_items.reduce((sum, item) => sum + item.quantity * cents(item.price), 0);
        const taxOnTop = order.tax_rate != null ? 0 : cents(order.tax_amount);
        const total = Math.max(0, subtotal + cents(formData.shipping_amount) - cents(order.discount_amount) + taxOnTop);
        const tax = order.tax_rate != null ? Math.round((total * order.tax_rate) / (100 + order.tax_rate)) : cents(order.tax_amount);

        return { subtotal: subtotal / 100, tax: tax / 100, total: total / 100 };
    })();

    const removeOrderItem = (itemId: number) => {
        setFormData((prev) => ({
            ...prev,
            order_items: prev.order_items.filter((item) => item.id !== itemId),
        }));
    };

    const hasValidationError = (fieldName: string): boolean => {
        return validationErrors.some((error) => error.toLowerCase().includes(fieldName.toLowerCase()));
    };

    const clearValidationErrors = () => {
        setValidationErrors([]);
    };

    const validateForm = (): { isValid: boolean; errors: string[] } => {
        const errors: string[] = [];

        // Check required fields
        if (!formData.status || formData.status.trim() === '') {
            errors.push('Order status is required');
        }

        // Check if at least one order item exists
        if (!formData.order_items || formData.order_items.length === 0) {
            errors.push('At least one order item is required');
        }

        // Validate order items
        formData.order_items.forEach((item, index) => {
            if (!item.quantity || item.quantity <= 0) {
                errors.push(`Item ${index + 1}: Quantity must be greater than 0`);
            }
            if (Number(item.price) < 0) {
                errors.push(`Item ${index + 1}: Price cannot be negative`);
            }
        });

        // Validate billing address if it exists
        if (formData.billing_address) {
            const required = ['first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country'];
            required.forEach((field) => {
                const value = formData.billing_address?.[field as keyof Address];
                if (!value || value.toString().trim() === '') {
                    errors.push(`Billing address: ${field.replace('_', ' ')} is required`);
                }
            });
        }

        // Validate shipping address if it exists
        if (formData.shipping_address) {
            const required = ['first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country'];
            required.forEach((field) => {
                const value = formData.shipping_address?.[field as keyof Address];
                if (!value || value.toString().trim() === '') {
                    errors.push(`Shipping address: ${field.replace('_', ' ')} is required`);
                }
            });
        }

        return {
            isValid: errors.length === 0,
            errors,
        };
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (processing) return;

        // Validate form before submission
        const validation = validateForm();
        if (!validation.isValid) {
            setValidationErrors(validation.errors);
            addToast({
                type: 'error',
                title: 'Validation Error',
                description: validation.errors[0], // Show the first error
                duration: 5000,
            });
            return;
        }

        // Clear validation errors if form is valid
        setValidationErrors([]);

        // Check if status is being manually changed to shipped without a tracking number
        const isChangingToShipped = formData.status === 'shipped' && order.status !== 'shipped';
        const hasTrackingNumber = formData.tracking_number && formData.tracking_number.trim() !== '';

        if (isChangingToShipped && !hasTrackingNumber) {
            // Show confirmation modal for manual status change to shipped
            setShowShipConfirmation(true);
            return;
        }

        // Proceed with submission
        submitForm();
    };

    const submitForm = () => {
        // Never the payment status, and no totals: the server derives them. Lines and shipping only while unpaid.
        const updateData = {
            status: formData.status,
            notes: formData.notes,
            tracking_number: formData.tracking_number,
            // only sent when carriers are configured, so nothing changes for installations without any
            ...(carriers.length > 0 ? { tracking_carrier: formData.tracking_carrier || null } : {}),
            ...(financials_locked
                ? {}
                : {
                      shipping_amount: formData.shipping_amount,
                      order_items: JSON.stringify(
                          formData.order_items.map((item) => ({ id: item.id, quantity: item.quantity, price: Number(item.price) })),
                      ),
                  }),
            billing_address: formData.billing_address ? JSON.stringify(formData.billing_address) : null,
            shipping_address: formData.shipping_address ? JSON.stringify(formData.shipping_address) : null,
        };

        setProcessing(true);

        router.patch(`/admin/orders/${order.id}`, updateData, {
            // the outcome (success, warning or error) is the controller flash, toasted by the layout
            onSuccess: (page) => {
                setProcessing(false);
                setShowShipConfirmation(false);

                // Back to the order, unless the controller reported a failure (then stay and let the admin retry)
                const flash = (page.props as { flash?: { error?: string | null } }).flash;
                if (!flash?.error) {
                    router.visit(`/admin/orders/${order.id}`);
                }
            },
            onError: () => {
                setProcessing(false);
            },
            onFinish: () => {
                // This will run regardless of success or error
                setTimeout(() => setProcessing(false), 1500);
            },
        });
    };

    const updateBillingAddress = (field: keyof Address, value: string) => {
        if (!formData.billing_address) return;

        setFormData((prev) => ({
            ...prev,
            billing_address: prev.billing_address
                ? {
                      ...prev.billing_address,
                      [field]: value,
                  }
                : null,
        }));
    };

    const updateShippingAddress = (field: keyof Address, value: string) => {
        if (!formData.shipping_address) return;

        setFormData((prev) => ({
            ...prev,
            shipping_address: prev.shipping_address
                ? {
                      ...prev.shipping_address,
                      [field]: value,
                  }
                : null,
        }));
    };

    return (
        <AppLayout>
            <Head title={`Edit Order #${order.order_number}`} />

            <div className="space-y-6 p-4 sm:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-white">Edit Order #{order.order_number}</h1>
                        <p className="text-gray-500">Last updated: {new Date(order.updated_at).toLocaleDateString()}</p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Order Details Section */}
                    <Collapsible open={openSections.orderDetails} onOpenChange={() => toggleSection('orderDetails')}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer pb-4 hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Package className="h-5 w-5 text-blue-600" />
                                            <CardTitle className="text-lg">Order Details</CardTitle>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${openSections.orderDetails ? 'rotate-180' : ''}`} />
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div>
                                            <Label htmlFor="status">Order Status</Label>
                                            <Select
                                                value={formData.status}
                                                onValueChange={(value: string) => {
                                                    setFormData((prev) => ({ ...prev, status: value }));
                                                    clearValidationErrors();
                                                }}
                                            >
                                                <SelectTrigger className={hasValidationError('order status') ? 'border-red-500' : ''}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {/* current status + forward moves; cancel and "undo ship" are confirmed actions on the order page */}
                                                    {statusOptions
                                                        .filter(
                                                            (option) =>
                                                                option.value === order.status ||
                                                                forwardTransitions(order.status, allowed_transitions).includes(option.value),
                                                        )
                                                        .map((option) => (
                                                            <SelectItem key={option.value} value={option.value}>
                                                                {option.label}
                                                            </SelectItem>
                                                        ))}
                                                </SelectContent>
                                            </Select>
                                            {hasValidationError('order status') && (
                                                <p className="mt-1 text-sm text-red-500">Order status is required</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label>Payment Status</Label>
                                            {/* read-only: it follows Stripe and refunds */}
                                            <div className="mt-2">
                                                <Badge variant="secondary">{order.payment_status}</Badge>
                                            </div>
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="tracking_number">Tracking Number</Label>
                                        <Input
                                            id="tracking_number"
                                            value={formData.tracking_number}
                                            onChange={(e) => setFormData((prev) => ({ ...prev, tracking_number: e.target.value }))}
                                            placeholder="Enter tracking number"
                                        />
                                    </div>
                                    {carriers.length > 0 && (
                                        <div>
                                            <Label htmlFor="tracking_carrier">{t('tracking_admin.carrier')}</Label>
                                            <Select
                                                value={formData.tracking_carrier || NO_CARRIER}
                                                onValueChange={(value) =>
                                                    setFormData((prev) => ({ ...prev, tracking_carrier: value === NO_CARRIER ? '' : value }))
                                                }
                                            >
                                                <SelectTrigger id="tracking_carrier">
                                                    <SelectValue placeholder={t('tracking_admin.carrier_select')} />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {carriers.map((carrier) => (
                                                        <SelectItem key={carrier.code} value={carrier.code}>
                                                            {carrier.name}
                                                        </SelectItem>
                                                    ))}
                                                    <SelectItem value={NO_CARRIER}>{t('tracking_admin.carrier_unknown')}</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}
                                    <div>
                                        <Label htmlFor="notes">Order Notes</Label>
                                        <Textarea
                                            id="notes"
                                            value={formData.notes}
                                            onChange={(e) => setFormData((prev) => ({ ...prev, notes: e.target.value }))}
                                            placeholder="Add any notes about this order..."
                                            rows={3}
                                        />
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Order Items Section */}
                    <Collapsible open={openSections.orderItems} onOpenChange={() => toggleSection('orderItems')}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer pb-4 hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Package className="h-5 w-5 text-blue-600" />
                                            <CardTitle className="text-lg">Order Items</CardTitle>
                                            <Badge variant="secondary">{formData.order_items.length} items</Badge>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${openSections.orderItems ? 'rotate-180' : ''}`} />
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent>
                                    <div className="space-y-4">
                                        {financials_locked && <p className="text-sm text-muted-foreground">{t('admin.orders.financials_locked')}</p>}
                                        {formData.order_items.map((item) => (
                                            <div
                                                key={item.id}
                                                className="flex flex-col gap-4 rounded-lg border p-4 sm:flex-row sm:items-center sm:justify-between"
                                            >
                                                <div className="flex items-center gap-4">
                                                    {item.product.image_url && (
                                                        <img
                                                            src={item.product.image_url}
                                                            alt={item.product.name}
                                                            className="h-12 w-12 flex-shrink-0 rounded object-cover"
                                                        />
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <h4 className="truncate font-medium">{item.product.name}</h4>
                                                        <p className="text-sm text-gray-600">{formatCurrency(item.price)} each</p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center justify-between gap-4 sm:justify-end">
                                                    <div className="flex items-center gap-2">
                                                        <Label htmlFor={`quantity-${item.id}`} className="text-sm whitespace-nowrap">
                                                            Qty:
                                                        </Label>
                                                        <Input
                                                            id={`quantity-${item.id}`}
                                                            type="number"
                                                            min="1"
                                                            step="1"
                                                            value={item.quantity}
                                                            onChange={(e) => updateOrderItemQuantity(item.id, parseInt(e.target.value) || 1)}
                                                            className="w-20"
                                                            disabled={financials_locked}
                                                        />
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="font-medium">{formatCurrency(item.quantity * Number(item.price))}</p>
                                                    </div>
                                                    {/* the server deletes a removed line (unpaid orders only) */}
                                                    {!financials_locked && formData.order_items.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="sm"
                                                            onClick={() => removeOrderItem(item.id)}
                                                            className="whitespace-nowrap text-red-600 hover:text-red-800"
                                                        >
                                                            Remove
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        ))}

                                        {hasValidationError('order item') && formData.order_items.length === 0 && (
                                            <div className="rounded-lg border border-red-200 bg-red-50 p-4">
                                                <p className="text-sm text-red-600">At least one order item is required</p>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Financial Information Section */}
                    <Collapsible open={openSections.financial} onOpenChange={() => toggleSection('financial')}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer pb-4 hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <DollarSign className="h-5 w-5 text-green-600" />
                                            <CardTitle className="text-lg">Financial Summary</CardTitle>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${openSections.financial ? 'rotate-180' : ''}`} />
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                        <div className="space-y-4">
                                            <div>
                                                <Label htmlFor="shipping_amount">Shipping Amount</Label>
                                                <Input
                                                    id="shipping_amount"
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    disabled={financials_locked}
                                                    value={formData.shipping_amount}
                                                    onChange={(e) =>
                                                        setFormData((prev) => ({ ...prev, shipping_amount: parseFloat(e.target.value) || 0 }))
                                                    }
                                                />
                                            </div>
                                            {financials_locked && (
                                                <p className="text-sm text-muted-foreground">{t('admin.orders.financials_locked')}</p>
                                            )}
                                        </div>
                                        <div>
                                            <h3 className="mb-4 font-medium">Order Summary</h3>
                                            <div className="space-y-2 rounded-lg p-4">
                                                <div className="flex justify-between text-sm">
                                                    <span>Subtotal:</span>
                                                    <span>{formatCurrency(preview.subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span>Shipping:</span>
                                                    <span>{formatCurrency(formData.shipping_amount)}</span>
                                                </div>
                                                {Number(order.discount_amount ?? 0) > 0 && (
                                                    <div className="flex justify-between text-sm">
                                                        <span>Discount:</span>
                                                        <span>-{formatCurrency(Number(order.discount_amount))}</span>
                                                    </div>
                                                )}
                                                <div className="flex justify-between text-sm">
                                                    <span>{order.tax_rate != null ? `Included VAT (${order.tax_rate}%):` : 'Tax:'}</span>
                                                    <span>{formatCurrency(preview.tax)}</span>
                                                </div>
                                                <Separator />
                                                <div className="flex justify-between text-lg font-medium">
                                                    <span>Total:</span>
                                                    <span>{formatCurrency(preview.total)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Addresses Section */}
                    <Collapsible open={openSections.addresses} onOpenChange={() => toggleSection('addresses')}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer pb-4 hover:bg-gray-50">
                                    <div className="flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <MapPin className="h-5 w-5 text-red-600" />
                                            <CardTitle className="text-lg">Addresses</CardTitle>
                                        </div>
                                        <ChevronDown className={`h-4 w-4 transition-transform ${openSections.addresses ? 'rotate-180' : ''}`} />
                                    </div>
                                </CardHeader>
                            </CollapsibleTrigger>
                            <CollapsibleContent>
                                <CardContent className="space-y-6">
                                    {/* Billing Address */}
                                    <div>
                                        <h3 className="mb-4 font-medium">Billing Address</h3>
                                        {formData.billing_address && (
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div>
                                                    <Label htmlFor="billing_first_name">First Name</Label>
                                                    <Input
                                                        id="billing_first_name"
                                                        value={formData.billing_address.first_name}
                                                        onChange={(e) => updateBillingAddress('first_name', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="billing_last_name">Last Name</Label>
                                                    <Input
                                                        id="billing_last_name"
                                                        value={formData.billing_address.last_name}
                                                        onChange={(e) => updateBillingAddress('last_name', e.target.value)}
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <Label htmlFor="billing_address_line_1">Address Line 1</Label>
                                                    <Input
                                                        id="billing_address_line_1"
                                                        value={formData.billing_address.address_line_1}
                                                        onChange={(e) => updateBillingAddress('address_line_1', e.target.value)}
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <Label htmlFor="billing_address_line_2">Address Line 2</Label>
                                                    <Input
                                                        id="billing_address_line_2"
                                                        value={formData.billing_address.address_line_2 || ''}
                                                        onChange={(e) => updateBillingAddress('address_line_2', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="billing_city">City</Label>
                                                    <Input
                                                        id="billing_city"
                                                        value={formData.billing_address.city}
                                                        onChange={(e) => updateBillingAddress('city', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="billing_postal_code">Postal Code</Label>
                                                    <Input
                                                        id="billing_postal_code"
                                                        value={formData.billing_address.postal_code}
                                                        onChange={(e) => updateBillingAddress('postal_code', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="billing_state">State</Label>
                                                    <Input
                                                        id="billing_state"
                                                        value={formData.billing_address.state}
                                                        onChange={(e) => updateBillingAddress('state', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="billing_country">Country</Label>
                                                    <Input
                                                        id="billing_country"
                                                        value={formData.billing_address.country}
                                                        onChange={(e) => updateBillingAddress('country', e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>

                                    {/* Shipping Address */}
                                    <div>
                                        <h3 className="mb-4 font-medium">Shipping Address</h3>
                                        {formData.shipping_address && (
                                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                                <div>
                                                    <Label htmlFor="shipping_first_name">First Name</Label>
                                                    <Input
                                                        id="shipping_first_name"
                                                        value={formData.shipping_address.first_name}
                                                        onChange={(e) => updateShippingAddress('first_name', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="shipping_last_name">Last Name</Label>
                                                    <Input
                                                        id="shipping_last_name"
                                                        value={formData.shipping_address.last_name}
                                                        onChange={(e) => updateShippingAddress('last_name', e.target.value)}
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <Label htmlFor="shipping_address_line_1">Address Line 1</Label>
                                                    <Input
                                                        id="shipping_address_line_1"
                                                        value={formData.shipping_address.address_line_1}
                                                        onChange={(e) => updateShippingAddress('address_line_1', e.target.value)}
                                                    />
                                                </div>
                                                <div className="md:col-span-2">
                                                    <Label htmlFor="shipping_address_line_2">Address Line 2</Label>
                                                    <Input
                                                        id="shipping_address_line_2"
                                                        value={formData.shipping_address.address_line_2 || ''}
                                                        onChange={(e) => updateShippingAddress('address_line_2', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="shipping_city">City</Label>
                                                    <Input
                                                        id="shipping_city"
                                                        value={formData.shipping_address.city}
                                                        onChange={(e) => updateShippingAddress('city', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="shipping_postal_code">Postal Code</Label>
                                                    <Input
                                                        id="shipping_postal_code"
                                                        value={formData.shipping_address.postal_code}
                                                        onChange={(e) => updateShippingAddress('postal_code', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="shipping_state">State</Label>
                                                    <Input
                                                        id="shipping_state"
                                                        value={formData.shipping_address.state}
                                                        onChange={(e) => updateShippingAddress('state', e.target.value)}
                                                    />
                                                </div>
                                                <div>
                                                    <Label htmlFor="shipping_country">Country</Label>
                                                    <Input
                                                        id="shipping_country"
                                                        value={formData.shipping_address.country}
                                                        onChange={(e) => updateShippingAddress('country', e.target.value)}
                                                    />
                                                </div>
                                            </div>
                                        )}
                                    </div>
                                </CardContent>
                            </CollapsibleContent>
                        </Card>
                    </Collapsible>

                    {/* Save Button */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit(`/admin/orders/${order.id}`)}
                            className="order-2 sm:order-1"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing} className="order-1 flex items-center justify-center gap-2 sm:order-2">
                            {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                            <span>{processing ? 'Saving...' : 'Save Changes'}</span>
                        </Button>
                    </div>
                </form>

                {/* Confirmation Modal for Manual Status Change to Shipped */}
                <Dialog open={showShipConfirmation} onOpenChange={setShowShipConfirmation}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Mark Order as Shipped?</DialogTitle>
                            <DialogDescription>
                                You are about to mark this order as shipped without a tracking number.
                                A shipping notification email will be sent to the customer without tracking information.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setShowShipConfirmation(false)}>
                                Cancel
                            </Button>
                            <Button onClick={submitForm} disabled={processing}>
                                {processing ? (
                                    <>
                                        <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                                        Processing...
                                    </>
                                ) : (
                                    'Confirm & Send Email'
                                )}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}

import { useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';
import { Separator } from '@/components/ui/separator';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/contexts/ToastContext';
import { 
    ChevronDown, 
    Package, 
    DollarSign, 
    User, 
    MapPin,
    Save,
    LoaderCircle
} from 'lucide-react';

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
    total_amount: number;
    notes?: string;
    created_at: string;
    updated_at: string;
    user: User;
    order_items: OrderItem[];
    billing_address?: Address;
    shipping_address?: Address;
    tracking_number?: string;
    shipping_method?: string;
    estimated_delivery_date?: string;
}

interface FormData {
    status: string;
    payment_status: string;
    subtotal: number;
    shipping_amount: number;
    tax_amount: number;
    total_amount: number;
    notes: string;
    order_items: OrderItem[];
    billing_address: Address | null;
    shipping_address: Address | null;
    tracking_number: string;
}

interface Props {
    order: Order;
}

const statusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'processing', label: 'Processing' },
    { value: 'shipped', label: 'Shipped' },
    { value: 'delivered', label: 'Delivered' },
    { value: 'cancelled', label: 'Cancelled' },
];

const paymentStatusOptions = [
    { value: 'pending', label: 'Pending' },
    { value: 'paid', label: 'Paid' },
    { value: 'failed', label: 'Failed' },
    { value: 'refunded', label: 'Refunded' },
];

export default function Edit({ order }: Props) {
    const { addToast } = useToast();
    const [processing, setProcessing] = useState(false);
    const [validationErrors, setValidationErrors] = useState<string[]>([]);
    const [formData, setFormData] = useState<FormData>({
        status: order.status,
        payment_status: order.payment_status,
        subtotal: order.subtotal,
        shipping_amount: order.shipping_amount,
        tax_amount: order.tax_amount,
        total_amount: order.total_amount,
        notes: order.notes || '',
        order_items: order.order_items,
        billing_address: order.billing_address || null,
        shipping_address: order.shipping_address || null,
        tracking_number: order.tracking_number || '',
    });

    const [openSections, setOpenSections] = useState({
        orderDetails: true,
        orderItems: true,
        financial: true,
        addresses: true,
    });

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: 'EUR',
        }).format(amount);
    };

    const toggleSection = (section: keyof typeof openSections) => {
        setOpenSections(prev => ({ ...prev, [section]: !prev[section] }));
    };

    const updateOrderItemQuantity = (itemId: number, newQuantity: number) => {
        if (newQuantity < 1) return;
        
        setFormData(prev => ({
            ...prev,
            order_items: prev.order_items.map(item => 
                item.id === itemId 
                    ? { 
                        ...item, 
                        quantity: newQuantity,
                        total: item.price * newQuantity
                    }
                    : item
            )
        }));
        
        // Clear validation errors when user fixes quantity issues
        clearValidationErrors();
    };

    const calculateTotals = () => {
        const itemsTotal = formData.order_items.reduce((sum, item) => sum + item.total, 0);
        const newSubtotal = itemsTotal;
        const newTotal = newSubtotal + formData.shipping_amount + formData.tax_amount;
        
        setFormData(prev => ({
            ...prev,
            subtotal: newSubtotal,
            total_amount: newTotal
        }));
    };

    const removeOrderItem = (itemId: number) => {
        setFormData(prev => ({
            ...prev,
            order_items: prev.order_items.filter(item => item.id !== itemId)
        }));
    };

    const hasValidationError = (fieldName: string): boolean => {
        return validationErrors.some(error => 
            error.toLowerCase().includes(fieldName.toLowerCase())
        );
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

        if (!formData.payment_status || formData.payment_status.trim() === '') {
            errors.push('Payment status is required');
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
            if (!item.price || item.price <= 0) {
                errors.push(`Item ${index + 1}: Price must be greater than 0`);
            }
        });

        // Validate billing address if it exists
        if (formData.billing_address) {
            const required = ['first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country'];
            required.forEach(field => {
                const value = formData.billing_address?.[field as keyof Address];
                if (!value || value.toString().trim() === '') {
                    errors.push(`Billing address: ${field.replace('_', ' ')} is required`);
                }
            });
        }

        // Validate shipping address if it exists
        if (formData.shipping_address) {
            const required = ['first_name', 'last_name', 'address_line_1', 'city', 'postal_code', 'country'];
            required.forEach(field => {
                const value = formData.shipping_address?.[field as keyof Address];
                if (!value || value.toString().trim() === '') {
                    errors.push(`Shipping address: ${field.replace('_', ' ')} is required`);
                }
            });
        }

        return {
            isValid: errors.length === 0,
            errors
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
                duration: 5000
            });
            return;
        }

        // Clear validation errors if form is valid
        setValidationErrors([]);
        
        calculateTotals();
        
        const updateData = {
            status: formData.status,
            payment_status: formData.payment_status,
            subtotal: formData.subtotal,
            shipping_amount: formData.shipping_amount,
            tax_amount: formData.tax_amount,
            total_amount: formData.total_amount,
            notes: formData.notes,
            tracking_number: formData.tracking_number,
            order_items: JSON.stringify(formData.order_items.map(item => ({
                id: item.id,
                product_id: item.product_id,
                quantity: item.quantity,
                price: item.price,
                total: item.total
            }))),
            billing_address: formData.billing_address ? JSON.stringify(formData.billing_address) : null,
            shipping_address: formData.shipping_address ? JSON.stringify(formData.shipping_address) : null,
        };

        setProcessing(true);

        router.patch(`/admin/orders/${order.id}`, updateData, {
            onSuccess: () => {
                setProcessing(false);
                addToast({
                    type: 'success',
                    title: 'Order Updated',
                    description: 'Order details have been successfully updated.',
                    duration: 3000
                });
                
                // Wait a moment for the toast to show before redirecting
                setTimeout(() => {
                    router.visit(`/admin/orders/${order.id}`);
                }, 1000);
            },
            onError: () => {
                setProcessing(false);
                addToast({
                    type: 'error',
                    title: 'Update Failed',
                    description: 'There was an error updating the order. Please try again.',
                    duration: 5000
                });
            },
            onFinish: () => {
                // This will run regardless of success or error
                setTimeout(() => setProcessing(false), 1500);
            }
        });
    };

    const updateBillingAddress = (field: keyof Address, value: string) => {
        if (!formData.billing_address) return;
        
        setFormData(prev => ({
            ...prev,
            billing_address: prev.billing_address ? {
                ...prev.billing_address,
                [field]: value
            } : null
        }));
    };

    const updateShippingAddress = (field: keyof Address, value: string) => {
        if (!formData.shipping_address) return;
        
        setFormData(prev => ({
            ...prev,
            shipping_address: prev.shipping_address ? {
                ...prev.shipping_address,
                [field]: value
            } : null
        }));
    };

    return (
        <AppLayout>
            <Head title={`Edit Order #${order.order_number}`} />
            
            <div className="p-4 sm:p-6 space-y-6">
                {/* Header */}
                <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold text-white">
                            Edit Order #{order.order_number}
                        </h1>
                        <p className="text-gray-500">
                            Last updated: {new Date(order.updated_at).toLocaleDateString()}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    {/* Order Details Section */}
                    <Collapsible open={openSections.orderDetails} onOpenChange={() => toggleSection('orderDetails')}>
                        <Card>
                            <CollapsibleTrigger asChild>
                                <CardHeader className="cursor-pointer hover:bg-gray-50 pb-4">
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
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <Label htmlFor="status">Order Status</Label>
                                            <Select 
                                                value={formData.status} 
                                                onValueChange={(value: string) => {
                                                    setFormData(prev => ({ ...prev, status: value }));
                                                    clearValidationErrors();
                                                }}
                                            >
                                                <SelectTrigger className={hasValidationError('order status') ? 'border-red-500' : ''}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {statusOptions.map(option => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {hasValidationError('order status') && (
                                                <p className="text-sm text-red-500 mt-1">Order status is required</p>
                                            )}
                                        </div>
                                        <div>
                                            <Label htmlFor="payment_status">Payment Status</Label>
                                            <Select 
                                                value={formData.payment_status} 
                                                onValueChange={(value: string) => {
                                                    setFormData(prev => ({ ...prev, payment_status: value }));
                                                    clearValidationErrors();
                                                }}
                                            >
                                                <SelectTrigger className={hasValidationError('payment status') ? 'border-red-500' : ''}>
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {paymentStatusOptions.map(option => (
                                                        <SelectItem key={option.value} value={option.value}>
                                                            {option.label}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {hasValidationError('payment status') && (
                                                <p className="text-sm text-red-500 mt-1">Payment status is required</p>
                                            )}
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="tracking_number">Tracking Number</Label>
                                        <Input
                                            id="tracking_number"
                                            value={formData.tracking_number}
                                            onChange={(e) => setFormData(prev => ({ ...prev, tracking_number: e.target.value }))}
                                            placeholder="Enter tracking number"
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="notes">Order Notes</Label>
                                        <Textarea
                                            id="notes"
                                            value={formData.notes}
                                            onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
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
                                <CardHeader className="cursor-pointer hover:bg-gray-50 pb-4">
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
                                        {formData.order_items.map((item) => (
                                            <div key={item.id} className="flex flex-col sm:flex-row sm:items-center sm:justify-between p-4 border rounded-lg gap-4">
                                                <div className="flex items-center gap-4">
                                                    {item.product.image_url && (
                                                        <img 
                                                            src={item.product.image_url} 
                                                            alt={item.product.name}
                                                            className="w-12 h-12 object-cover rounded flex-shrink-0"
                                                        />
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <h4 className="font-medium truncate">{item.product.name}</h4>
                                                        <p className="text-sm text-gray-600">
                                                            {formatCurrency(item.price)} each
                                                        </p>
                                                    </div>
                                                </div>
                                                <div className="flex items-center justify-between sm:justify-end gap-4">
                                                    <div className="flex items-center gap-2">
                                                        <Label htmlFor={`quantity-${item.id}`} className="text-sm whitespace-nowrap">Qty:</Label>
                                                        <Input
                                                            id={`quantity-${item.id}`}
                                                            type="number"
                                                            min="1"
                                                            value={item.quantity}
                                                            onChange={(e) => updateOrderItemQuantity(item.id, parseInt(e.target.value) || 1)}
                                                            className="w-20"
                                                        />
                                                    </div>
                                                    <div className="text-right">
                                                        <p className="font-medium">{formatCurrency(item.total)}</p>
                                                    </div>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => removeOrderItem(item.id)}
                                                        className="text-red-600 hover:text-red-800 whitespace-nowrap"
                                                    >
                                                        Remove
                                                    </Button>
                                                </div>
                                            </div>
                                        ))}
                                        
                                        {hasValidationError('order item') && formData.order_items.length === 0 && (
                                            <div className="p-4 border-red-200 bg-red-50 border rounded-lg">
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
                                <CardHeader className="cursor-pointer hover:bg-gray-50 pb-4">
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
                                    <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div className="space-y-4">
                                            <div>
                                                <Label htmlFor="shipping_amount">Shipping Amount</Label>
                                                <Input
                                                    id="shipping_amount"
                                                    type="number"
                                                    step="0.01"
                                                    value={formData.shipping_amount}
                                                    onChange={(e) => setFormData(prev => ({ ...prev, shipping_amount: parseFloat(e.target.value) || 0 }))}
                                                />
                                            </div>
                                            <div>
                                                <Label htmlFor="tax_amount">Tax Amount</Label>
                                                <Input
                                                    id="tax_amount"
                                                    type="number"
                                                    step="0.01"
                                                    value={formData.tax_amount}
                                                    onChange={(e) => setFormData(prev => ({ ...prev, tax_amount: parseFloat(e.target.value) || 0 }))}
                                                />
                                            </div>
                                        </div>
                                        <div>
                                            <h3 className="font-medium mb-4">Order Summary</h3>
                                            <div className="space-y-2 p-4 rounded-lg">
                                                <div className="flex justify-between text-sm">
                                                    <span>Subtotal:</span>
                                                    <span>{formatCurrency(formData.subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span>Shipping:</span>
                                                    <span>{formatCurrency(formData.shipping_amount)}</span>
                                                </div>
                                                <div className="flex justify-between text-sm">
                                                    <span>Tax:</span>
                                                    <span>{formatCurrency(formData.tax_amount)}</span>
                                                </div>
                                                <Separator />
                                                <div className="flex justify-between font-medium text-lg">
                                                    <span>Total:</span>
                                                    <span>{formatCurrency(formData.total_amount)}</span>
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
                                <CardHeader className="cursor-pointer hover:bg-gray-50 pb-4">
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
                                        <h3 className="font-medium mb-4">Billing Address</h3>
                                        {formData.billing_address && (
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                        <h3 className="font-medium mb-4">Shipping Address</h3>
                                        {formData.shipping_address && (
                                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                    <div className="flex flex-col sm:flex-row sm:justify-end gap-4">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => router.visit(`/admin/orders/${order.id}`)}
                            className="order-2 sm:order-1"
                        >
                            Cancel
                        </Button>
                        <Button 
                            type="submit" 
                            disabled={processing}
                            className="flex items-center justify-center gap-2 order-1 sm:order-2"
                        >
                            {processing ? (
                                <LoaderCircle className="h-4 w-4 animate-spin" />
                            ) : (
                                <Save className="h-4 w-4" />
                            )}
                            <span>{processing ? 'Saving...' : 'Save Changes'}</span>
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

import { useState } from 'react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { router } from '@inertiajs/react';
import { Trash2, Plus, Minus } from 'lucide-react';

interface OrderItem {
    id: number;
    product: {
        id: string | number;
        name: string;
        image_url?: string;
    };
    quantity: number;
    price: number;
    total: number;
}

interface Address {
    id: number;
    first_name: string;
    last_name: string;
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
    tax_amount: number;
    shipping_amount: number;
    total_amount: number;
    currency: string;
    notes?: string;
    orderItems: OrderItem[];
    billingAddress?: Address;
    shippingAddress?: Address;
}

interface EditOrderDialogProps {
    order: Order;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export default function EditOrderDialog({ order, open, onOpenChange }: EditOrderDialogProps) {
    const [formData, setFormData] = useState({
        status: order.status,
        payment_status: order.payment_status,
        shipping_amount: order.shipping_amount,
        tax_amount: order.tax_amount,
        notes: order.notes || '',
        orderItems: [...order.orderItems],
        billingAddress: order.billingAddress ? { ...order.billingAddress } : null,
        shippingAddress: order.shippingAddress ? { ...order.shippingAddress } : null,
    });
    const [isSubmitting, setIsSubmitting] = useState(false);

    const statusOptions = [
        { value: 'pending', label: 'Pending' },
        { value: 'processing', label: 'Processing' },
        { value: 'shipped', label: 'Shipped' },
        { value: 'delivered', label: 'Delivered' },
        { value: 'cancelled', label: 'Cancelled' },
    ];

    const paymentStatusOptions = [
        { value: 'pending', label: 'Pending' },
        { value: 'processing', label: 'Processing' },
        { value: 'succeeded', label: 'Succeeded' },
        { value: 'failed', label: 'Failed' },
    ];

    const updateItemQuantity = (itemId: number, newQuantity: number) => {
        if (newQuantity < 1) return;
        
        setFormData(prev => ({
            ...prev,
            orderItems: prev.orderItems.map(item => 
                item.id === itemId 
                    ? { ...item, quantity: newQuantity, total: item.price * newQuantity }
                    : item
            )
        }));
    };

    const removeItem = (itemId: number) => {
        setFormData(prev => ({
            ...prev,
            orderItems: prev.orderItems.filter(item => item.id !== itemId)
        }));
    };

    const updateAddress = (type: 'billing' | 'shipping', field: string, value: string) => {
        const addressKey = type === 'billing' ? 'billingAddress' : 'shippingAddress';
        setFormData(prev => ({
            ...prev,
            [addressKey]: prev[addressKey] ? {
                ...prev[addressKey],
                [field]: value
            } : null
        }));
    };

    const calculateSubtotal = () => {
        return formData.orderItems.reduce((sum, item) => sum + item.total, 0);
    };

    const calculateTotal = () => {
        return calculateSubtotal() + formData.shipping_amount + formData.tax_amount;
    };

    const handleSubmit = () => {
        setIsSubmitting(true);
        
        const updateData = {
            status: formData.status,
            payment_status: formData.payment_status,
            shipping_amount: formData.shipping_amount,
            tax_amount: formData.tax_amount,
            notes: formData.notes,
            subtotal: calculateSubtotal(),
            total_amount: calculateTotal(),
            order_items: formData.orderItems.map(item => ({
                id: item.id,
                quantity: item.quantity,
                price: item.price,
                total: item.total,
            })),
            billing_address: formData.billingAddress,
            shipping_address: formData.shippingAddress,
        };

        router.patch(`/admin/orders/${order.id}`, updateData, {
            onSuccess: () => {
                onOpenChange(false);
                setIsSubmitting(false);
            },
            onError: () => {
                setIsSubmitting(false);
            }
        });
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('it-IT', {
            style: 'currency',
            currency: order.currency || 'EUR',
        }).format(amount);
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-4xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>Edit Order {order.order_number}</DialogTitle>
                </DialogHeader>

                <div className="space-y-6">
                    {/* Status Updates */}
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="status">Order Status</Label>
                            <Select value={formData.status} onValueChange={(value) => setFormData(prev => ({ ...prev, status: value }))}>
                                <SelectTrigger>
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
                        </div>
                        <div>
                            <Label htmlFor="payment_status">Payment Status</Label>
                            <Select value={formData.payment_status} onValueChange={(value) => setFormData(prev => ({ ...prev, payment_status: value }))}>
                                <SelectTrigger>
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
                        </div>
                    </div>

                    <Separator />

                    {/* Order Items */}
                    <div>
                        <Label className="text-base font-semibold">Order Items</Label>
                        <div className="space-y-3 mt-2">
                            {formData.orderItems.map((item) => (
                                <div key={item.id} className="flex items-center space-x-4 p-3 border rounded-lg">
                                    <div className="flex-1">
                                        <p className="font-medium">{item.product.name}</p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatCurrency(item.price)} each
                                        </p>
                                    </div>
                                    <div className="flex items-center space-x-2">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => updateItemQuantity(item.id, item.quantity - 1)}
                                            disabled={item.quantity <= 1}
                                        >
                                            <Minus className="h-3 w-3" />
                                        </Button>
                                        <span className="w-8 text-center">{item.quantity}</span>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => updateItemQuantity(item.id, item.quantity + 1)}
                                        >
                                            <Plus className="h-3 w-3" />
                                        </Button>
                                    </div>
                                    <div className="w-20 text-right">
                                        <p className="font-medium">{formatCurrency(item.total)}</p>
                                    </div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() => removeItem(item.id)}
                                        className="text-red-600 hover:text-red-700"
                                        disabled={formData.orderItems.length <= 1}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                    </div>

                    <Separator />

                    {/* Pricing */}
                    <div className="grid grid-cols-2 gap-4">
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

                    {/* Order Summary */}
                    <div className="bg-muted p-4 rounded-lg">
                        <div className="space-y-2">
                            <div className="flex justify-between">
                                <span>Subtotal</span>
                                <span>{formatCurrency(calculateSubtotal())}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Shipping</span>
                                <span>{formatCurrency(formData.shipping_amount)}</span>
                            </div>
                            <div className="flex justify-between">
                                <span>Tax</span>
                                <span>{formatCurrency(formData.tax_amount)}</span>
                            </div>
                            <Separator />
                            <div className="flex justify-between font-semibold">
                                <span>Total</span>
                                <span>{formatCurrency(calculateTotal())}</span>
                            </div>
                        </div>
                    </div>

                    <Separator />

                    {/* Addresses */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Billing Address */}
                        {formData.billingAddress && (
                            <div>
                                <Label className="text-base font-semibold">Billing Address</Label>
                                <div className="space-y-3 mt-2">
                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label htmlFor="billing_first_name">First Name</Label>
                                            <Input
                                                id="billing_first_name"
                                                value={formData.billingAddress.first_name}
                                                onChange={(e) => updateAddress('billing', 'first_name', e.target.value)}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="billing_last_name">Last Name</Label>
                                            <Input
                                                id="billing_last_name"
                                                value={formData.billingAddress.last_name}
                                                onChange={(e) => updateAddress('billing', 'last_name', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="billing_address_1">Address Line 1</Label>
                                        <Input
                                            id="billing_address_1"
                                            value={formData.billingAddress.address_line_1}
                                            onChange={(e) => updateAddress('billing', 'address_line_1', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="billing_address_2">Address Line 2</Label>
                                        <Input
                                            id="billing_address_2"
                                            value={formData.billingAddress.address_line_2 || ''}
                                            onChange={(e) => updateAddress('billing', 'address_line_2', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label htmlFor="billing_city">City</Label>
                                            <Input
                                                id="billing_city"
                                                value={formData.billingAddress.city}
                                                onChange={(e) => updateAddress('billing', 'city', e.target.value)}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="billing_postal_code">Postal Code</Label>
                                            <Input
                                                id="billing_postal_code"
                                                value={formData.billingAddress.postal_code}
                                                onChange={(e) => updateAddress('billing', 'postal_code', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Shipping Address */}
                        {formData.shippingAddress && (
                            <div>
                                <Label className="text-base font-semibold">Shipping Address</Label>
                                <div className="space-y-3 mt-2">
                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label htmlFor="shipping_first_name">First Name</Label>
                                            <Input
                                                id="shipping_first_name"
                                                value={formData.shippingAddress.first_name}
                                                onChange={(e) => updateAddress('shipping', 'first_name', e.target.value)}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="shipping_last_name">Last Name</Label>
                                            <Input
                                                id="shipping_last_name"
                                                value={formData.shippingAddress.last_name}
                                                onChange={(e) => updateAddress('shipping', 'last_name', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                    <div>
                                        <Label htmlFor="shipping_address_1">Address Line 1</Label>
                                        <Input
                                            id="shipping_address_1"
                                            value={formData.shippingAddress.address_line_1}
                                            onChange={(e) => updateAddress('shipping', 'address_line_1', e.target.value)}
                                        />
                                    </div>
                                    <div>
                                        <Label htmlFor="shipping_address_2">Address Line 2</Label>
                                        <Input
                                            id="shipping_address_2"
                                            value={formData.shippingAddress.address_line_2 || ''}
                                            onChange={(e) => updateAddress('shipping', 'address_line_2', e.target.value)}
                                        />
                                    </div>
                                    <div className="grid grid-cols-2 gap-2">
                                        <div>
                                            <Label htmlFor="shipping_city">City</Label>
                                            <Input
                                                id="shipping_city"
                                                value={formData.shippingAddress.city}
                                                onChange={(e) => updateAddress('shipping', 'city', e.target.value)}
                                            />
                                        </div>
                                        <div>
                                            <Label htmlFor="shipping_postal_code">Postal Code</Label>
                                            <Input
                                                id="shipping_postal_code"
                                                value={formData.shippingAddress.postal_code}
                                                onChange={(e) => updateAddress('shipping', 'postal_code', e.target.value)}
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}
                    </div>

                    <Separator />

                    {/* Notes */}
                    <div>
                        <Label htmlFor="notes">Notes</Label>
                        <Textarea
                            id="notes"
                            value={formData.notes}
                            onChange={(e) => setFormData(prev => ({ ...prev, notes: e.target.value }))}
                            rows={3}
                        />
                    </div>

                    {/* Actions */}
                    <div className="flex justify-end space-x-2">
                        <Button 
                            variant="outline" 
                            onClick={() => onOpenChange(false)}
                            disabled={isSubmitting}
                        >
                            Cancel
                        </Button>
                        <Button 
                            onClick={handleSubmit}
                            disabled={isSubmitting}
                        >
                            {isSubmitting ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}

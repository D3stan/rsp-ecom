import { ConfirmDialog } from '@/components/admin/settings/ConfirmDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { Mail, UserCheck, UserX } from 'lucide-react';
import { useState } from 'react';
import { type CustomerRow, money } from './Index';

interface Props {
    customer: CustomerRow & {
        phone: string | null;
        email_verified: boolean;
        addresses: { id: number; type: string; name: string; lines: string[] }[];
    };
    orders: { id: number; order_number: string; status: string; payment_status: string; total_amount: number; created_at: string | null }[];
}

export default function CustomerShow({ customer, orders }: Props) {
    const [confirming, setConfirming] = useState(false);
    const [busy, setBusy] = useState(false);
    const form = useForm({
        name: customer.name,
        email: customer.email,
        phone: customer.phone ?? '',
        email_verified: customer.email_verified,
    });

    const toggleActive = () =>
        router.patch(
            route('admin.customers.toggle-active', customer.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setConfirming(false);
                },
            },
        );

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: 'Customers', href: route('admin.customers.index') },
                { title: customer.name, href: route('admin.customers.show', customer.id) },
            ]}
        >
            <Head title={customer.name} />
            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{customer.name}</h1>
                        <p className="text-muted-foreground">
                            {customer.orders_count} orders · {money(customer.total_spent)} paid
                        </p>
                        <div className="mt-2 space-x-1">
                            <Badge variant={customer.is_active ? 'default' : 'outline'}>{customer.is_active ? 'Active' : 'Deactivated'}</Badge>
                            {!customer.verified && <Badge variant="secondary">Unverified</Badge>}
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" onClick={() => router.post(route('admin.customers.reset-link', customer.id), {}, { preserveScroll: true })}>
                            <Mail className="mr-2 h-4 w-4" /> Send password reset link
                        </Button>
                        <Button variant={customer.is_active ? 'destructive' : 'default'} onClick={() => setConfirming(true)}>
                            {customer.is_active ? <UserX className="mr-2 h-4 w-4" /> : <UserCheck className="mr-2 h-4 w-4" />}
                            {customer.is_active ? 'Deactivate' : 'Reactivate'}
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.patch(route('admin.customers.update', customer.id), { preserveScroll: true });
                            }}
                            className="grid gap-4 md:grid-cols-2"
                        >
                            {(['name', 'email', 'phone'] as const).map((field) => (
                                <div key={field} className="space-y-1">
                                    <Label htmlFor={field} className="capitalize">
                                        {field}
                                    </Label>
                                    <Input id={field} value={form.data[field]} onChange={(e) => form.setData(field, e.target.value)} />
                                    {form.errors[field] && <p className="text-sm text-destructive">{form.errors[field]}</p>}
                                </div>
                            ))}
                            <div className="flex items-center gap-2 self-end pb-2">
                                <input
                                    id="email_verified"
                                    type="checkbox"
                                    checked={form.data.email_verified}
                                    onChange={(e) => form.setData('email_verified', e.target.checked)}
                                />
                                <Label htmlFor="email_verified">Email verified</Label>
                            </div>
                            <div className="md:col-span-2">
                                <Button type="submit" disabled={form.processing}>
                                    Save changes
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Orders</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2">
                            {orders.length === 0 && <p className="text-muted-foreground">No orders yet.</p>}
                            {orders.map((o) => (
                                <Link
                                    key={o.id}
                                    href={route('admin.orders.show', o.id)}
                                    className="flex items-center justify-between gap-2 rounded border p-3 hover:bg-muted/50"
                                >
                                    <span className="font-medium">#{o.order_number}</span>
                                    <span className="text-sm text-muted-foreground">
                                        {o.status} · {o.payment_status}
                                    </span>
                                    <span className="tabular-nums">{money(o.total_amount)}</span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Addresses</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {customer.addresses.length === 0 && <p className="text-muted-foreground">No saved addresses.</p>}
                            {customer.addresses.map((a) => (
                                <div key={a.id} className="rounded border p-3 text-sm">
                                    <p className="font-medium">
                                        {a.name} <span className="font-normal text-muted-foreground">({a.type})</span>
                                    </p>
                                    {a.lines.map((l) => (
                                        <p key={l}>{l}</p>
                                    ))}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>

            <ConfirmDialog
                open={confirming}
                title={customer.is_active ? 'Deactivate customer?' : 'Reactivate customer?'}
                description={
                    customer.is_active
                        ? `${customer.name} will be signed out and unable to log in. Orders and data are kept.`
                        : `${customer.name} will be able to log in again.`
                }
                confirmLabel={customer.is_active ? 'Deactivate' : 'Reactivate'}
                destructive={customer.is_active}
                busy={busy}
                onConfirm={toggleActive}
                onCancel={() => setConfirming(false)}
            />
        </AdminLayout>
    );
}

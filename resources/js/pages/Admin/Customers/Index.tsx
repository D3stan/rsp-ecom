import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

export interface CustomerRow {
    id: number;
    name: string;
    email: string;
    is_active: boolean;
    verified: boolean;
    orders_count: number;
    total_spent: number;
    created_at: string | null;
}

interface Props {
    customers: { data: CustomerRow[]; current_page: number; last_page: number; total: number };
    filters: { search: string };
}

export const money = (value: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'EUR' }).format(value);

export default function CustomersIndex({ customers, filters }: Props) {
    const [search, setSearch] = useState(filters.search);

    const load = (params: { search?: string; page?: number }) =>
        router.get(
            route('admin.customers.index'),
            Object.fromEntries(Object.entries({ search: filters.search, ...params }).filter(([, v]) => v !== '' && v !== undefined && v !== 1)),
            { preserveState: true, preserveScroll: true },
        );

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        load({ search: search.trim(), page: undefined });
    };

    return (
        <AdminLayout breadcrumbs={[{ title: 'Admin', href: '/admin/dashboard' }, { title: 'Customers', href: route('admin.customers.index') }]}>
            <Head title="Customers" />
            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">Customers</h1>
                    <p className="text-muted-foreground">{customers.total} registered customers</p>
                </div>

                <form onSubmit={submit} className="flex gap-2">
                    <label htmlFor="customer-search" className="sr-only">
                        Search customers
                    </label>
                    <Input id="customer-search" type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by name or email" />
                    <Button type="submit" variant="secondary">
                        <Search className="h-4 w-4" />
                        <span className="sr-only sm:not-sr-only sm:ml-2">Search</span>
                    </Button>
                </form>

                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-4 font-medium">Customer</th>
                                    <th className="p-4 font-medium">Status</th>
                                    <th className="p-4 text-right font-medium">Orders</th>
                                    <th className="hidden p-4 text-right font-medium md:table-cell">Paid total</th>
                                    <th className="hidden p-4 font-medium lg:table-cell">Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                {customers.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            No customers found.
                                        </td>
                                    </tr>
                                )}
                                {customers.data.map((c) => (
                                    <tr key={c.id} className="border-b hover:bg-muted/50">
                                        <td className="max-w-[18rem] p-4">
                                            <Link href={route('admin.customers.show', c.id)} className="block truncate font-medium hover:underline">
                                                {c.name}
                                            </Link>
                                            <span className="block truncate text-muted-foreground">{c.email}</span>
                                        </td>
                                        <td className="space-x-1 p-4">
                                            <Badge variant={c.is_active ? 'default' : 'outline'}>{c.is_active ? 'Active' : 'Deactivated'}</Badge>
                                            {!c.verified && <Badge variant="secondary">Unverified</Badge>}
                                        </td>
                                        <td className="p-4 text-right tabular-nums">{c.orders_count}</td>
                                        <td className="hidden p-4 text-right tabular-nums md:table-cell">{money(c.total_spent)}</td>
                                        <td className="hidden p-4 tabular-nums lg:table-cell">{c.created_at ? new Date(c.created_at).toLocaleDateString() : '–'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {customers.last_page > 1 && (
                        <div className="flex items-center justify-between gap-4 p-4">
                            <p className="text-sm text-muted-foreground">
                                Page {customers.current_page} of {customers.last_page}
                            </p>
                            <div className="flex gap-2">
                                <Button variant="outline" size="sm" disabled={customers.current_page <= 1} onClick={() => load({ page: customers.current_page - 1 })}>
                                    <ChevronLeft className="h-4 w-4" /> Previous
                                </Button>
                                <Button variant="outline" size="sm" disabled={customers.current_page >= customers.last_page} onClick={() => load({ page: customers.current_page + 1 })}>
                                    Next <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}

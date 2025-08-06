import React from 'react';
import { Head, Link } from '@inertiajs/react';

// Simple currency formatter
const formatCurrency = (amount: string | number) => {
    const num = typeof amount === 'string' ? parseFloat(amount) : amount;
    return new Intl.NumberFormat('it-IT', {
        style: 'currency',
        currency: 'EUR',
    }).format(num / 100); // Stripe amounts are in cents
};

interface Subscription {
    id: string;
    name: string;
    stripe_status: string;
    stripe_price: string;
    quantity: number;
    created_at: string;
    ends_at?: string;
    trial_ends_at?: string;
    items: Array<{
        id: string;
        stripe_price: string;
        quantity: number;
    }>;
}

interface Props {
    subscriptions: Subscription[];
}

const Index: React.FC<Props> = ({ subscriptions }) => {
    const getStatusBadge = (status: string) => {
        const statusClasses = {
            active: 'bg-green-100 text-green-800',
            canceled: 'bg-red-100 text-red-800', 
            past_due: 'bg-yellow-100 text-yellow-800',
            incomplete: 'bg-gray-100 text-gray-800',
            trialing: 'bg-blue-100 text-blue-800',
        };

        return (
            <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusClasses[status as keyof typeof statusClasses] || 'bg-gray-100 text-gray-800'}`}>
                {status}
            </span>
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    My Subscriptions
                </h2>
            }
        >
            <Head title="My Subscriptions" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="p-6 text-gray-900">
                            {subscriptions.length === 0 ? (
                                <div className="text-center py-12">
                                    <svg
                                        className="mx-auto h-12 w-12 text-gray-400"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        aria-hidden="true"
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            strokeWidth={2}
                                            d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"
                                        />
                                    </svg>
                                    <h3 className="mt-2 text-sm font-medium text-gray-900">No subscriptions</h3>
                                    <p className="mt-1 text-sm text-gray-500">
                                        You don't have any active subscriptions yet.
                                    </p>
                                    <div className="mt-6">
                                        <Link
                                            href="/products"
                                            className="inline-flex items-center rounded-md border border-transparent bg-indigo-600 px-4 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            Browse Products
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="space-y-6">
                                    <div className="flex items-center justify-between">
                                        <h3 className="text-lg font-medium text-gray-900">Your Subscriptions</h3>
                                        <Link
                                            href="/products"
                                            className="inline-flex items-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                        >
                                            Browse More Products
                                        </Link>
                                    </div>

                                    <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
                                        {subscriptions.map((subscription) => (
                                            <div
                                                key={subscription.id}
                                                className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow"
                                            >
                                                <div className="p-6">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="text-lg font-medium text-gray-900">
                                                            {subscription.name}
                                                        </h4>
                                                        {getStatusBadge(subscription.stripe_status)}
                                                    </div>
                                                    
                                                    <div className="mt-4 space-y-2">
                                                        <p className="text-sm text-gray-600">
                                                            Price: {formatCurrency(subscription.stripe_price)}
                                                        </p>
                                                        <p className="text-sm text-gray-600">
                                                            Quantity: {subscription.quantity}
                                                        </p>
                                                        <p className="text-sm text-gray-600">
                                                            Started: {new Date(subscription.created_at).toLocaleDateString()}
                                                        </p>
                                                        {subscription.trial_ends_at && (
                                                            <p className="text-sm text-blue-600">
                                                                Trial ends: {new Date(subscription.trial_ends_at).toLocaleDateString()}
                                                            </p>
                                                        )}
                                                        {subscription.ends_at && (
                                                            <p className="text-sm text-red-600">
                                                                Ends: {new Date(subscription.ends_at).toLocaleDateString()}
                                                            </p>
                                                        )}
                                                    </div>

                                                    <div className="mt-6 flex space-x-3">
                                                        {subscription.stripe_status === 'active' && (
                                                            <button
                                                                onClick={() => {
                                                                    if (confirm('Are you sure you want to cancel this subscription?')) {
                                                                        // Handle cancellation
                                                                        window.location.href = `/subscription/cancel?subscription_id=${subscription.id}`;
                                                                    }
                                                                }}
                                                                className="flex-1 rounded-md border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 shadow-sm hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                                                            >
                                                                Cancel
                                                            </button>
                                                        )}
                                                        {subscription.stripe_status === 'canceled' && !subscription.ends_at && (
                                                            <button
                                                                onClick={() => {
                                                                    window.location.href = `/subscription/resume?subscription_id=${subscription.id}`;
                                                                }}
                                                                className="flex-1 rounded-md border border-transparent bg-indigo-600 px-3 py-2 text-sm font-medium text-white shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                                                            >
                                                                Resume
                                                            </button>
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
};

export default Index;

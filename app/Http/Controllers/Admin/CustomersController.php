<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin > Customers: list, detail, edit, deactivate/reactivate, send a password-reset link.
 * "Delete" is deactivation: orders and history are kept, and the account can no longer sign in.
 */
class CustomersController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $customers = User::customers()
            ->withCount('orders')
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereIn('payment_status', Order::SHIPPABLE_PAYMENT_STATUSES)], 'total_amount')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return Inertia::render('Admin/Customers/Index', [
            'customers' => $customers->through(fn (User $user) => $this->row($user)),
            'filters' => ['search' => $search],
        ]);
    }

    public function show(User $customer): Response
    {
        $this->ensureCustomer($customer);

        $customer->loadCount('orders')
            ->loadSum(['orders as total_spent' => fn ($q) => $q->whereIn('payment_status', Order::SHIPPABLE_PAYMENT_STATUSES)], 'total_amount')
            ->load('addresses');

        return Inertia::render('Admin/Customers/Show', [
            'customer' => $this->row($customer) + [
                'phone' => $customer->phone,
                'email_verified' => $customer->email_verified_at !== null,
                'addresses' => $customer->addresses->map(fn ($a) => [
                    'id' => $a->id,
                    'type' => $a->type,
                    'name' => trim("{$a->first_name} {$a->last_name}"),
                    'lines' => array_values(array_filter([$a->address_line_1, $a->address_line_2, trim("{$a->postal_code} {$a->city}"), $a->country])),
                ])->values(),
            ],
            'orders' => $customer->orders()->latest('id')->limit(50)->get(['id', 'order_number', 'status', 'payment_status', 'total_amount', 'created_at'])
                ->map(fn (Order $o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'payment_status' => $o->payment_status,
                    'total_amount' => (float) $o->total_amount,
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values(),
        ]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        $this->ensureCustomer($customer);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'email_verified' => ['required', 'boolean'],
        ]);

        $emailChanged = $data['email'] !== $customer->email;
        $customer->fill(['name' => $data['name'], 'email' => $data['email'], 'phone' => $data['phone'] ?? null]);

        // A new address is unverified unless the admin says otherwise.
        if (! $data['email_verified']) {
            $customer->email_verified_at = null;
        } elseif ($emailChanged || $customer->email_verified_at === null) {
            $customer->email_verified_at = now();
        }
        $customer->save();

        return back()->with('success', 'Customer updated.');
    }

    public function toggleActive(User $customer): RedirectResponse
    {
        $this->ensureCustomer($customer);

        $customer->update(['is_active' => ! $customer->is_active]);

        return back()->with('success', $customer->is_active ? 'Customer reactivated.' : 'Customer deactivated.');
    }

    public function sendResetLink(User $customer): RedirectResponse
    {
        $this->ensureCustomer($customer);

        $status = Password::sendResetLink(['email' => $customer->email]);

        return $status === Password::RESET_LINK_SENT
            ? back()->with('success', 'Password reset link sent.')
            : back()->with('error', 'Could not send the reset link. Try again in a minute.');
    }

    /** Admins are not managed here. */
    private function ensureCustomer(User $user): void
    {
        abort_unless($user->isCustomer(), 404);
    }

    private function row(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => (bool) $user->is_active,
            'verified' => $user->email_verified_at !== null,
            'orders_count' => (int) $user->orders_count,
            'total_spent' => (float) ($user->total_spent ?? 0),
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}

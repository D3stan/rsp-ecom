<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\User;
use App\Support\AddressRules;
use App\Support\ItalianProvinces;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The customer's address book (shipping addresses). Entries are separate from the addresses stored on orders, so
 * editing or deleting one never changes an order.
 */
class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('settings/addresses', [
            'addresses' => $this->book($request->user())->orderByDesc('is_default')->orderByDesc('id')->get()
                ->map(fn (Address $address) => $address->only(['id', 'is_default', ...Address::CHECKOUT_FIELDS]))->all(),
            'provinces' => ItalianProvinces::ALL,
            'max' => User::MAX_SAVED_ADDRESSES,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validate(AddressRules::for() + ['is_default' => 'sometimes|boolean']);

        if ($this->book($user)->count() >= User::MAX_SAVED_ADDRESSES) {
            throw ValidationException::withMessages(['address_line_1' => str_replace('{max}', (string) User::MAX_SAVED_ADDRESSES, __('account.addresses.limit_reached'))]);
        }

        DB::transaction(function () use ($user, $data) {
            // the first address is the default; later ones only when asked to be
            $default = ($data['is_default'] ?? false) || ! $this->book($user)->exists();
            unset($data['is_default']);

            if ($default) {
                $this->book($user)->update(['is_default' => false]);
            }

            $user->addresses()->create($data + ['type' => 'shipping', 'is_default' => $default]);
        });

        return back()->with('success', __('account.addresses.saved'));
    }

    public function update(Request $request, int $address): RedirectResponse
    {
        $entry = $this->book($request->user())->findOrFail($address);
        $data = $request->validate(AddressRules::for() + ['is_default' => 'sometimes|boolean']);

        DB::transaction(function () use ($request, $entry, $data) {
            $default = (bool) ($data['is_default'] ?? $entry->is_default);
            unset($data['is_default']);

            if ($default && ! $entry->is_default) {
                $this->book($request->user())->update(['is_default' => false]);
            }

            // Clearing the default flag of the only default would leave the book without one: keep it
            $entry->update($data + ['is_default' => $default || $entry->is_default]);
        });

        return back()->with('success', __('account.addresses.saved'));
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $entry = $this->book($request->user())->findOrFail($address);

        DB::transaction(function () use ($request, $entry) {
            $entry->delete();

            // The newest remaining address takes over as the default
            if ($entry->is_default) {
                $this->book($request->user())->latest('id')->first()?->update(['is_default' => true]);
            }
        });

        return back()->with('success', __('account.addresses.deleted'));
    }

    private function book(User $user)
    {
        return $user->addresses()->shipping();
    }
}

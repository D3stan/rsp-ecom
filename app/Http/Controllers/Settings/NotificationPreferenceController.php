<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'notify_back_in_stock' => ['required', 'boolean'],
        ]);

        $request->user()->update(['notify_back_in_stock' => (bool) $validated['notify_back_in_stock']]);

        return to_route('profile.edit');
    }
}

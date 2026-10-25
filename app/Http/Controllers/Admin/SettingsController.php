<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSettingsRequest;
use App\Settings\Settings;
use App\Settings\SystemInfo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;
use Throwable;

class SettingsController extends Controller
{
    public function __construct(private readonly Settings $settings) {}

    public function index(SystemInfo $system): Response
    {
        Gate::authorize('manage-settings');

        return Inertia::render('Admin/Settings/Index', [
            'groups' => $this->settings->groups(),
            'system' => $system->panel(),
            'historyLimit' => 20,
        ]);
    }

    public function update(UpdateSettingsRequest $request, string $group): RedirectResponse
    {
        try {
            $changed = $this->settings->setMany($request->settingValues(), $request->user());
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('settings.ui.save_failed'));
        }

        return $changed === []
            ? back()->with('warning', __('settings.ui.no_changes'))
            : back()->with('success', __('settings.ui.saved'));
    }

    public function reset(Request $request, string $key): RedirectResponse
    {
        Gate::authorize('manage-settings');

        try {
            $this->settings->reset($key, $request->user());
        } catch (InvalidArgumentException) {
            abort(404);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('settings.ui.save_failed'));
        }

        return back()->with('success', __('settings.ui.reset_done'));
    }

    public function history(string $group): JsonResponse
    {
        Gate::authorize('manage-settings');

        try {
            return response()->json(['data' => $this->settings->history($group, 20)]);
        } catch (InvalidArgumentException) {
            abort(404);
        }
    }
}

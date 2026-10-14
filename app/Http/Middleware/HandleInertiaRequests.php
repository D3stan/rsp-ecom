<?php

namespace App\Http\Middleware;

use App\Settings\Settings;
use App\Support\Locales;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return [
            ...parent::share($request),
            'name' => settings('store.name'),
            'supportedLocales' => Locales::supported(),
            // Admin settings marked public in config/settings.php, typed (numbers, booleans, lists), keyed by setting key
            'settings' => fn (): array => app(Settings::class)->public(),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'csrf_token' => $request->hasSession() ? csrf_token() : '',
            'ziggy' => fn (): array => [
                ...(new Ziggy)->toArray(),
                'location' => $request->url(),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Session flashes set by controllers (->with('success'|'error'|'warning', ...)); rendered as toasts by the
            // FlashToaster. `id` is fresh per flash (null when there is no message) so the client shows each one once.
            'flash' => function () use ($request): array {
                $flash = [
                    'success' => $this->flashMessage($request, 'success'),
                    'error' => $this->flashMessage($request, 'error'),
                    'warning' => $this->flashMessage($request, 'warning'),
                ];

                return [...$flash, 'id' => array_filter($flash) !== [] ? (string) Str::uuid() : null];
            },
        ];
    }

    /**
     * A flashed message as plain text, or null when there is none (or it is not a string).
     */
    private function flashMessage(Request $request, string $key): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $message = $request->session()->get($key);

        return is_string($message) && $message !== '' ? $message : null;
    }
}

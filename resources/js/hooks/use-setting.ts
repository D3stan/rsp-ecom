import { type PublicSettings, type SettingKey, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';

/**
 * All settings marked `public` in config/settings.php (shared Inertia prop `settings`).
 * Safe when the prop is missing: returns an empty object.
 */
export function useSettings(): Partial<PublicSettings> {
    const { settings } = usePage<SharedData>().props;

    return settings ?? {};
}

/**
 * One public setting by its catalogue key. Returns `undefined` when the value is not available,
 * or the optional fallback.
 *
 * const days = useSetting('orders.public_link_days', 30);
 */
export function useSetting<K extends SettingKey>(key: K): PublicSettings[K] | undefined;
export function useSetting<K extends SettingKey>(key: K, fallback: PublicSettings[K]): PublicSettings[K];
export function useSetting<K extends SettingKey>(key: K, fallback?: PublicSettings[K]): PublicSettings[K] | undefined {
    const settings = useSettings();
    const value = settings[key];

    return value === undefined || value === null ? fallback : value;
}

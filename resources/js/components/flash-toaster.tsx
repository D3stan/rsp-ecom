import { useToast } from '@/contexts/ToastContext';
import useTranslation from '@/hooks/useTranslation';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';

const STORAGE_KEY = 'flash-toaster:shown';
const REMEMBER_LAST = 20;

// Ids of the flashes already toasted. Module-level, so two layouts/pages mounting for the same visit, or
// React re-running the effect, never show a flash twice; and Inertia's history restore on browser Back
// (which brings back the old props, id included) does not re-show a stale one.
const shown = new Set<string>();

function readStored(): string[] {
    try {
        const stored: unknown = JSON.parse(sessionStorage.getItem(STORAGE_KEY) ?? '[]');
        return Array.isArray(stored) ? stored.filter((value): value is string => typeof value === 'string') : [];
    } catch {
        // storage unavailable: the in-memory set is enough
        return [];
    }
}

function wasShown(id: string): boolean {
    if (shown.has(id)) return true;

    // survives a full page reload followed by Back
    if (readStored().includes(id)) {
        shown.add(id);
        return true;
    }

    return false;
}

function remember(id: string): void {
    shown.add(id);

    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify([...readStored(), id].slice(-REMEMBER_LAST)));
    } catch {
        // storage unavailable: the in-memory set is enough
    }
}

/**
 * Turns the controller session flashes (`->with('success'|'error'|'warning', ...)`, shared as `flash`
 * with a per-flash `id`) into toasts. Renders nothing; mount it once per layout.
 */
export default function FlashToaster() {
    const { flash } = usePage<SharedData>().props;
    const { addToast } = useToast();
    const { t } = useTranslation();

    const id = flash?.id ?? null;

    useEffect(() => {
        if (!flash || !id || wasShown(id)) return;

        remember(id);

        (['success', 'error', 'warning'] as const).forEach((type) => {
            const message = flash[type];
            if (message) {
                addToast({ type, title: t(`flash.${type}`), description: message, duration: type === 'success' ? 5000 : 8000 });
            }
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    return null;
}

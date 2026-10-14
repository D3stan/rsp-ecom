import { useSetting } from '@/hooks/use-setting';

/** "1-2", or just "2" when the range is a single value. */
export function formatRange(min: number, max: number): string {
    return min >= max ? String(max) : `${min}-${max}`;
}

/**
 * The numbers and the address the policy copy quotes ({days}, {dispatch}, {delivery}, {email} in lang/*.json).
 * They come from the settings (legal.withdrawal_days, shipping.*_days_*, contact.support_email), never from the
 * text itself, so the copy follows the admin page and the order emails.
 */
export function usePolicyValues() {
    const days = useSetting('legal.withdrawal_days', 14);
    const dispatchMin = useSetting('shipping.dispatch_days_min', 1);
    const dispatchMax = useSetting('shipping.dispatch_days_max', 2);
    const deliveryMin = useSetting('shipping.delivery_days_min', 3);
    const deliveryMax = useSetting('shipping.delivery_days_max', 5);
    const email = useSetting('contact.support_email', '');

    return {
        days,
        dispatch: formatRange(dispatchMin, dispatchMax),
        delivery: formatRange(deliveryMin, deliveryMax),
        email,
    };
}

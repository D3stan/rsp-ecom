import { usePage } from '@inertiajs/react';

/**
 * The variant to show first on a product page: the one asked for in the URL (?variant=ID, e.g. the "Buy now" link of
 * a back-in-stock email) when the product has it, else the fallback (the default variant).
 * Reads Inertia's page URL, so the server render and the browser agree.
 */
export function useInitialVariantId(variants: { id: number }[], fallback: number): number {
    const { url } = usePage();
    const requested = Number(new URLSearchParams(url.split('?')[1] ?? '').get('variant'));

    return variants.some((variant) => variant.id === requested) ? requested : fallback;
}

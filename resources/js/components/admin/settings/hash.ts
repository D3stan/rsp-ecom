// The active settings group lives in the URL hash (#tax) so reload and back/forward keep it.

export function readGroupHash(): string {
    if (typeof window === 'undefined') return '';

    try {
        return decodeURIComponent(window.location.hash.replace(/^#/, ''));
    } catch {
        return '';
    }
}

/** An Inertia visit to another URL (the PATCH redirect) drops the hash: put it back without adding a history entry. */
export function restoreGroupHash(key: string): void {
    if (typeof window === 'undefined' || window.location.hash === `#${key}`) return;

    window.history.replaceState(window.history.state, '', `${window.location.pathname}${window.location.search}#${key}`);
}

let applied = false;

// Read before any translator runs: the server sets <html lang> and the app never changes it.
const originalLang = typeof document !== 'undefined' ? document.documentElement.lang : '';

// shortcut: Edge is only caught once it has tagged a node with _msttexthash; widen if Edge reports slip through.
export function isPageTranslated(): boolean {
    const root = document.documentElement;

    return (
        root.classList.contains('translated-ltr') || // Chrome (Google Translate)
        root.classList.contains('translated-rtl') ||
        root.lang !== originalLang || // Safari, Firefox, Chrome
        document.querySelector('[_msttexthash], font') !== null // Edge, other translators (the app never renders <font>)
    );
}

export function isTranslatedPageError(error: unknown): boolean {
    return (
        error instanceof Error && error.name === 'NotFoundError' && /'(removeChild|insertBefore)' on 'Node'/.test(error.message) && isPageTranslated()
    );
}

// Browser translators move DOM nodes behind React's back, so React later fails to remove or anchor on a node that is no
// longer where it expects it. Only while the page is translated: skip the removal, and append instead of anchoring.
// Untranslated pages keep the native behaviour, so real unmount bugs still throw and reach Sentry.
export function guardAgainstBrowserTranslation(): void {
    if (applied || typeof Node === 'undefined') {
        return;
    }

    applied = true;

    const originalRemoveChild = Node.prototype.removeChild;
    const originalInsertBefore = Node.prototype.insertBefore;

    Node.prototype.removeChild = function <T extends Node>(this: Node, child: T): T {
        if (child.parentNode !== this && isPageTranslated()) {
            return child;
        }

        return originalRemoveChild.call(this, child) as T;
    };

    Node.prototype.insertBefore = function <T extends Node>(this: Node, newNode: T, referenceNode: Node | null): T {
        const anchor = referenceNode && referenceNode.parentNode !== this && isPageTranslated() ? null : referenceNode;

        return originalInsertBefore.call(this, newNode, anchor) as T;
    };
}

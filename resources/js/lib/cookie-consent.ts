// The optional interface preferences (theme, sidebar, language) are only stored in the browser while the visitor has
// accepted the "optional" cookie category. The server shares the current answer as the `cookieConsent` page prop.
const PREFERENCE_COOKIES = ['appearance', 'sidebar_state'];
const PREFERENCE_STORAGE_KEYS = ['appearance', 'locale'];

let preferencesAllowed = false;

export interface CookieConsentProps {
    preferences: boolean;
}

export function canStorePreferences(): boolean {
    return preferencesAllowed;
}

export function setPreferencesConsent(consent: CookieConsentProps | undefined): void {
    preferencesAllowed = Boolean(consent?.preferences);

    if (!preferencesAllowed) {
        clearPreferences();
    }
}

function clearPreferences(): void {
    PREFERENCE_COOKIES.forEach((name) => {
        document.cookie = `${name}=; path=/; max-age=0; SameSite=Lax`;
    });

    try {
        PREFERENCE_STORAGE_KEYS.forEach((key) => localStorage.removeItem(key));
    } catch {
        // Storage can be blocked by the browser; there is then nothing to clear.
    }
}

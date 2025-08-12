import React, { createContext, useContext, useEffect, useState } from 'react';

type Translations = {
    [key: string]: string | { [key: string]: string };
};

interface TranslationContextType {
    t: (key: string, replacements?: { [key: string]: string | number }) => string;
    locale: string;
    changeLocale: (newLocale: string) => void;
    isLoading: boolean;
    availableLocales: string[];
}

const TranslationContext = createContext<TranslationContextType | undefined>(undefined);

// Global cache for translations - persists across component remounts
const globalTranslationCache: { [locale: string]: Translations } = {};

// Available locales
const availableLocales = ['en', 'es', 'fr', 'de', 'it'];

// Detect browser language
function detectBrowserLanguage(): string | null {
    if (typeof window === 'undefined') return null;
    
    const browserLang = navigator.language || (navigator as any).userLanguage;
    if (!browserLang) return null;
    
    // Extract the language code (e.g., 'it-IT' -> 'it')
    const langCode = browserLang.toLowerCase().split('-')[0];
    
    // Check if the detected language is supported
    return availableLocales.includes(langCode) ? langCode : null;
}

interface TranslationProviderProps {
    children: React.ReactNode;
    initialTranslations?: Translations;
    initialLocale?: string;
}

export function TranslationProvider({ children, initialTranslations = {}, initialLocale = 'en' }: TranslationProviderProps) {
    const [locale, setLocale] = useState(() => {
        // Try to get from localStorage first, then detect from browser, then use initialLocale
        if (typeof window !== 'undefined') {
            const savedLocale = localStorage.getItem('locale');
            if (savedLocale) {
                return savedLocale;
            }
            
            // Detect browser language
            const browserLocale = detectBrowserLanguage();
            if (browserLocale) {
                return browserLocale;
            }
        }
        return initialLocale;
    });

    const [translations, setTranslations] = useState<Translations>(initialTranslations);
    const [isLoading, setIsLoading] = useState(false);

    // Cache initial translations
    useEffect(() => {
        if (Object.keys(initialTranslations).length > 0) {
            globalTranslationCache[initialLocale] = initialTranslations;
            // If current locale matches initial locale, use initial translations
            if (locale === initialLocale) {
                setTranslations(initialTranslations);
            }
        }
    }, [initialTranslations, initialLocale, locale]);

    // Load translations when locale changes
    useEffect(() => {
        // Only load if we don't have translations for the current locale
        if (!globalTranslationCache[locale] && locale !== initialLocale) {
            loadTranslations(locale);
        } else if (locale === initialLocale && Object.keys(translations).length === 0) {
            // Load initial locale if no translations are set
            loadTranslations(locale);
        } else if (globalTranslationCache[locale] && globalTranslationCache[locale] !== translations) {
            // Update translations if we have cached ones that are different
            setTranslations(globalTranslationCache[locale]);
        }
    }, [locale, initialLocale]);

    const loadTranslations = async (newLocale: string) => {
        // Check cache first
        if (globalTranslationCache[newLocale]) {
            setTranslations(globalTranslationCache[newLocale]);
            return Promise.resolve();
        }

        setIsLoading(true);
        try {
            const response = await fetch(`/api/translations/${newLocale}`);
            if (response.ok) {
                const data = await response.json();
                globalTranslationCache[newLocale] = data;
                setTranslations(data);
            } else {
                // Fallback to English if locale not found
                if (newLocale !== 'en' && !globalTranslationCache['en']) {
                    const fallbackResponse = await fetch('/api/translations/en');
                    if (fallbackResponse.ok) {
                        const fallbackData = await fallbackResponse.json();
                        globalTranslationCache['en'] = fallbackData;
                        setTranslations(fallbackData);
                    }
                } else if (globalTranslationCache['en']) {
                    setTranslations(globalTranslationCache['en']);
                }
            }
        } catch (error) {
            console.error('Error loading translations:', error);
            // Use cached English as fallback
            if (globalTranslationCache['en']) {
                setTranslations(globalTranslationCache['en']);
            }
        } finally {
            setIsLoading(false);
        }
    };

    const t = (key: string, replacements?: { [key: string]: string | number }): string => {
        const keys = key.split('.');
        let value: unknown = translations;

        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = (value as Record<string, unknown>)[k];
            } else {
                // If not found, try to get from cache
                if (globalTranslationCache[locale]) {
                    const cached = globalTranslationCache[locale];
                    let cachedValue: unknown = cached;
                    for (const ck of keys) {
                        if (cachedValue && typeof cachedValue === 'object' && ck in cachedValue) {
                            cachedValue = (cachedValue as Record<string, unknown>)[ck];
                        } else {
                            return key; // Return key if translation not found
                        }
                    }
                    value = cachedValue;
                    break;
                }
                return key; // Return key if translation not found
            }
        }

        if (typeof value !== 'string') {
            return key;
        }

        // Handle replacements with both {placeholder} and {{placeholder}} formats
        if (replacements && typeof value === 'string') {
            // Handle curly braces placeholder
            value = value.replace(/\{\{?(\w+)\}?\}/g, (match, placeholder) => replacements[placeholder]?.toString() || match);
        }

        return value as string;
    };

    const changeLocale = (newLocale: string) => {
        if (newLocale === locale) return;

        // Update the locale state and localStorage
        setLocale(newLocale);
        if (typeof window !== 'undefined') {
            localStorage.setItem('locale', newLocale);
        }

        // Send request to backend to update session and load translations
        const updateBackendLocale = async () => {
            try {
                // First, try to load translations (this will use cache if available)
                await loadTranslations(newLocale);
                
                // Then update backend session
                const response = await fetch('/change-language', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                    },
                    body: JSON.stringify({ locale: newLocale }),
                });
                
                if (!response.ok) {
                    console.warn('Failed to update backend locale, but frontend translation will still work');
                }
            } catch (error) {
                console.error('Failed to update locale:', error);
                // Still try to load translations for frontend
                await loadTranslations(newLocale);
            }
        };

        updateBackendLocale();
    };

    const contextValue: TranslationContextType = {
        t,
        locale,
        changeLocale,
        isLoading,
        availableLocales,
    };

    return <TranslationContext.Provider value={contextValue}>{children}</TranslationContext.Provider>;
}

export function useTranslation(): TranslationContextType {
    const context = useContext(TranslationContext);
    if (context === undefined) {
        throw new Error('useTranslation must be used within a TranslationProvider');
    }
    return context;
}

export default useTranslation;

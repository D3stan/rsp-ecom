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

interface TranslationProviderProps {
    children: React.ReactNode;
    initialTranslations?: Translations;
    initialLocale?: string;
}

export function TranslationProvider({ children, initialTranslations = {}, initialLocale = 'it' }: TranslationProviderProps) {
    const [locale, setLocale] = useState(() => {
        // Try to get from localStorage first, then use initialLocale
        if (typeof window !== 'undefined') {
            return localStorage.getItem('locale') || initialLocale;
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
        if (locale !== initialLocale || Object.keys(translations).length === 0) {
            loadTranslations(locale);
        }
    }, [locale, initialLocale, translations]);

    const loadTranslations = async (newLocale: string) => {
        // Check cache first
        if (globalTranslationCache[newLocale]) {
            setTranslations(globalTranslationCache[newLocale]);
            return;
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

        setLocale(newLocale);
        if (typeof window !== 'undefined') {
            localStorage.setItem('locale', newLocale);
        }

        // Load translations for new locale
        loadTranslations(newLocale).then(() => {
            // Reload page to apply new locale server-side
            if (typeof window !== 'undefined') {
                window.location.reload();
            }
        });
    };

    const contextValue: TranslationContextType = {
        t,
        locale,
        changeLocale,
        isLoading,
        availableLocales: ['en', 'es', 'fr', 'de', 'it'],
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

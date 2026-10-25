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

const globalTranslationCache: { [locale: string]: Translations } = {};

export const DEFAULT_SUPPORTED_LOCALES = ['en', 'es', 'fr', 'de', 'it'];

function detectBrowserLanguage(availableLocales: string[]): string | null {
    if (typeof window === 'undefined') return null;

    const nav = navigator as Navigator & { userLanguage?: string };
    const browserLang = nav.language || nav.userLanguage;

    if (!browserLang) return null;

    const langCode = browserLang.toLowerCase().split('-')[0];

    return availableLocales.includes(langCode) ? langCode : null;
}

interface TranslationProviderProps {
    children: React.ReactNode;
    initialTranslations?: Translations;
    initialLocale?: string;
    supportedLocales?: string[];
}

export function TranslationProvider({
    children,
    initialTranslations = {},
    initialLocale = 'en',
    supportedLocales = DEFAULT_SUPPORTED_LOCALES,
}: TranslationProviderProps) {
    const availableLocales = supportedLocales.length > 0 ? supportedLocales : DEFAULT_SUPPORTED_LOCALES;

    const [locale, setLocale] = useState(() => {
        if (typeof window !== 'undefined') {
            const savedLocale = localStorage.getItem('locale');
            if (savedLocale) {
                return savedLocale;
            }

            const browserLocale = detectBrowserLanguage(availableLocales);
            if (browserLocale) {
                return browserLocale;
            }
        }
        return initialLocale;
    });

    const [translations, setTranslations] = useState<Translations>(initialTranslations);
    const [isLoading, setIsLoading] = useState(false);

    useEffect(() => {
        if (Object.keys(initialTranslations).length > 0) {
            globalTranslationCache[initialLocale] = initialTranslations;
            if (locale === initialLocale) {
                setTranslations(initialTranslations);
            }
        }
    }, [initialTranslations, initialLocale, locale]);

    useEffect(() => {
        if (!globalTranslationCache[locale] && locale !== initialLocale) {
            loadTranslations(locale);
        } else if (locale === initialLocale && Object.keys(translations).length === 0) {
            loadTranslations(locale);
        } else if (globalTranslationCache[locale] && globalTranslationCache[locale] !== translations) {
            setTranslations(globalTranslationCache[locale]);
        }
    }, [locale, initialLocale, translations]);

    const loadTranslations = async (newLocale: string) => {
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
            if (globalTranslationCache['en']) {
                setTranslations(globalTranslationCache['en']);
            }
        } finally {
            setIsLoading(false);
        }
    };

    const t = (key: string, replacements?: { [key: string]: string | number }): string => {
        const getFallback = () => {
            if (typeof replacements?.defaultValue === 'string') {
                return replacements.defaultValue.replace(/\{\{?(\w+)\}?\}/g, (match, placeholder) => replacements[placeholder]?.toString() || match);
            }
            return key;
        };

        const direct = translations[key] ?? globalTranslationCache[locale]?.[key];
        if (typeof direct === 'string') {
            return replacements ? direct.replace(/\{\{?(\w+)\}?\}/g, (match, placeholder) => replacements[placeholder]?.toString() || match) : direct;
        }

        const keys = key.split('.');
        let value: unknown = translations;

        for (const k of keys) {
            if (value && typeof value === 'object' && k in value) {
                value = (value as Record<string, unknown>)[k];
            } else {
                if (globalTranslationCache[locale]) {
                    const cached = globalTranslationCache[locale];
                    let cachedValue: unknown = cached;
                    for (const ck of keys) {
                        if (cachedValue && typeof cachedValue === 'object' && ck in cachedValue) {
                            cachedValue = (cachedValue as Record<string, unknown>)[ck];
                        } else {
                            return getFallback();
                        }
                    }
                    value = cachedValue;
                    break;
                }
                return getFallback();
            }
        }

        if (typeof value !== 'string') {
            return getFallback();
        }

        if (replacements && typeof value === 'string') {
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

        const updateBackendLocale = async () => {
            try {
                await loadTranslations(newLocale);

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

import { useState, useEffect } from 'react';

type Translations = {
  [key: string]: string | { [key: string]: string };
};

const cachedTranslations: { [locale: string]: Translations } = {};

export const useTranslation = () => {
  const [locale, setLocale] = useState(() => {
    if (typeof window !== 'undefined') {
      return localStorage.getItem('locale') || 'en';
    }
    return 'en';
  });
  
  const [translations, setTranslations] = useState<Translations>({});
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    const loadTranslations = async () => {
      if (cachedTranslations[locale]) {
        setTranslations(cachedTranslations[locale]);
        return;
      }

      setIsLoading(true);
      try {
        const response = await fetch(`/api/translations/${locale}`);
        if (response.ok) {
          const data = await response.json();
          cachedTranslations[locale] = data;
          setTranslations(data);
        } else {
          // Fallback to English if locale not found
          const fallbackResponse = await fetch('/api/translations/en');
          if (fallbackResponse.ok) {
            const fallbackData = await fallbackResponse.json();
            cachedTranslations['en'] = fallbackData;
            setTranslations(fallbackData);
          }
        }
      } catch (error) {
        console.error('Error loading translations:', error);
      } finally {
        setIsLoading(false);
      }
    };

    loadTranslations();
  }, [locale]);

  const t = (key: string, replacements?: { [key: string]: string | number }) => {
    const keys = key.split('.');
    let value: unknown = translations;
    
    for (const k of keys) {
      if (value && typeof value === 'object' && k in value) {
        value = (value as Record<string, unknown>)[k];
      } else {
        return key; // Return key if translation not found
      }
    }
    
    if (typeof value !== 'string') {
      return key;
    }
    
    // Handle replacements like {{count}}
    if (replacements) {
      return value.replace(/\{\{(\w+)\}\}/g, (match, placeholder) => {
        return replacements[placeholder]?.toString() || match;
      });
    }
    
    return value;
  };

  const changeLocale = (newLocale: string) => {
    if (newLocale === locale) return;
    
    setIsLoading(true);
    setLocale(newLocale);
    if (typeof window !== 'undefined') {
      localStorage.setItem('locale', newLocale);
    }
    
    // Add a small delay to show loading state, then reload
    setTimeout(() => {
      if (typeof window !== 'undefined') {
        window.location.reload();
      }
    }, 300);
  };

  return {
    t,
    locale,
    changeLocale,
    isLoading,
    availableLocales: ['en', 'es', 'fr', 'de', 'it']
  };
};

export default useTranslation;

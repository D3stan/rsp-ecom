import React from 'react';
import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/react';
import * as Sentry from '@sentry/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import ErrorFallback from './components/error-fallback';
import FlashToaster from './components/flash-toaster';
import { ToastContainer } from './components/Toast';
import { ToastProvider } from './contexts/ToastContext';
import { TranslationProvider } from './contexts/TranslationContext';
import { initializeTheme } from './hooks/use-appearance';
import { type CookieConsentProps, setPreferencesConsent } from './lib/cookie-consent';
import { initSentry, type SentryConfig } from './lib/sentry';
import { guardAgainstBrowserTranslation } from './lib/translation-guard';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

guardAgainstBrowserTranslation();

// Wrapper component to provide context to all pages
function AppWrapper({ children, pageProps }: { children: React.ReactNode; pageProps: Record<string, unknown> }) {
    const translations = pageProps.translations as Record<string, string>;
    const locale = pageProps.locale as string;
    const supportedLocales = Array.isArray(pageProps.supportedLocales) ? (pageProps.supportedLocales as string[]) : undefined;

    return (
        <TranslationProvider initialTranslations={translations} initialLocale={locale} supportedLocales={supportedLocales}>
            <ToastProvider>
                <Sentry.ErrorBoundary fallback={<ErrorFallback />}>{children}</Sentry.ErrorBoundary>
                <ToastContainer />
            </ToastProvider>
        </TranslationProvider>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        // Before anything renders: the theme and language code reads it on first render
        setPreferencesConsent(props.initialPage.props.cookieConsent as CookieConsentProps | undefined);
        router.on('navigate', (event) => setPreferencesConsent(event.detail.page.props.cookieConsent as CookieConsentProps | undefined));

        initSentry(
            props.initialPage.props.sentry as SentryConfig | undefined,
            props.initialPage.props.auth as { user?: { id: number; role?: string } | null },
        );

        const root = createRoot(el);

        // Renders the page (this app has no persistent layouts) next to the FlashToaster: inside Inertia's page
        // context, mounted once for every page (storefront, account and admin) and kept across visits.
        root.render(
            <AppWrapper pageProps={props}>
                <App {...props}>
                    {({
                        Component,
                        props: pageProps,
                        key,
                    }: {
                        Component: React.ComponentType;
                        props: Record<string, unknown>;
                        key: React.Key | null;
                    }) => (
                        <>
                            <FlashToaster />
                            <Component key={key} {...pageProps} />
                        </>
                    )}
                </App>
            </AppWrapper>,
        );
    },
    progress: {
        color: '#4B5563',
    },
})
    .then(() => {
        // This will set light / dark mode on load...
        initializeTheme();
    })
    .catch((error) => {
        console.error('Failed to initialize Inertia app:', error);
    });

// This will set light / dark mode on load...
initializeTheme();

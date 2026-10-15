import React from 'react';
import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import FlashToaster from './components/flash-toaster';
import { ToastContainer } from './components/Toast';
import { ToastProvider } from './contexts/ToastContext';
import { TranslationProvider } from './contexts/TranslationContext';
import { initializeTheme } from './hooks/use-appearance';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Wrapper component to provide context to all pages
function AppWrapper({ children, pageProps }: { children: React.ReactNode; pageProps: Record<string, unknown> }) {
    const translations = pageProps.translations as Record<string, string>;
    const locale = pageProps.locale as string;
    const supportedLocales = Array.isArray(pageProps.supportedLocales) ? (pageProps.supportedLocales as string[]) : undefined;

    return (
        <TranslationProvider initialTranslations={translations} initialLocale={locale} supportedLocales={supportedLocales}>
            <ToastProvider>
                {children}
                <ToastContainer />
            </ToastProvider>
        </TranslationProvider>
    );
}

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
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

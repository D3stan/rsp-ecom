import '../css/app.css';
import React from 'react';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { initializeTheme } from './hooks/use-appearance';
import { ToastProvider } from './contexts/ToastContext';
import { TranslationProvider } from './contexts/TranslationContext';
import { ToastContainer } from './components/Toast';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

// Wrapper component to access Inertia props
function AppWrapper({ Component, props }: { Component: React.ComponentType<Record<string, unknown>>, props: Record<string, unknown> }) {
    const translations = props.translations || {};
    const locale = props.locale || 'en';
    
    return (
        <TranslationProvider initialTranslations={translations} initialLocale={locale}>
            <ToastProvider>
                <Component {...props} />
                <ToastContainer />
            </ToastProvider>
        </TranslationProvider>
    );
}

createInertiaApp({
    title: (title) => title ? `${title} - ${appName}` : appName,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(React.createElement(AppWrapper, { Component: App, props }));
    },
    progress: {
        color: '#4B5563',
    },
}).then(() => {
    // This will set light / dark mode on load...
    initializeTheme();
}).catch(error => {
    console.error('Failed to initialize Inertia app:', error);
});

// This will set light / dark mode on load...
initializeTheme();

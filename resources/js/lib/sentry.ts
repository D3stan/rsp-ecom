import { router } from '@inertiajs/react';
import * as Sentry from '@sentry/react';

export interface SentryConfig {
    dsn: string | null;
    environment: string;
    release: string | null;
    replay: boolean;
}

interface AuthProps {
    user?: { id: number; role?: string } | null;
}

let started = false;
let replayStarted = false;

export interface FeedbackLabels {
    title: string;
    messageLabel: string;
    submit: string;
    cancel: string;
    success: string;
}

export function isFeedbackAvailable(): boolean {
    return Boolean(Sentry.getFeedback());
}

// Opens Sentry's feedback form, tagging the report with the request reference the error page shows the visitor.
export async function openFeedbackForm(labels: FeedbackLabels, requestId?: string | null): Promise<void> {
    const feedback = Sentry.getFeedback();

    if (!feedback) {
        return;
    }

    if (requestId) {
        Sentry.setTag('request_id', requestId);
    }

    const form = await feedback.createForm({
        formTitle: labels.title,
        messageLabel: labels.messageLabel,
        submitButtonLabel: labels.submit,
        cancelButtonLabel: labels.cancel,
        successMessageText: labels.success,
    });

    form.appendToDom();
    form.open();
}

function tagVisitor(auth: AuthProps | undefined): void {
    const user = auth?.user;

    Sentry.setUser(user ? { id: String(user.id) } : null);
    Sentry.setTag('area', user ? (user.role === 'admin' ? 'admin' : 'customer') : 'guest');
}

// Replay stays off until the visitor accepts the `sentry_replay` cookie (shared by the server as `replay`). It only
// records the moments around an error, with every text, input and media element masked.
function startReplayIfConsented(config: SentryConfig | undefined): void {
    if (!config?.replay || replayStarted) {
        return;
    }

    replayStarted = true;
    Sentry.addIntegration(Sentry.replayIntegration({ maskAllText: true, maskAllInputs: true, blockAllMedia: true }));
}

export function initSentry(config: SentryConfig | undefined, auth: AuthProps | undefined): void {
    if (!config?.dsn || started) {
        return;
    }

    started = true;

    Sentry.init({
        dsn: config.dsn,
        environment: config.environment,
        release: config.release ?? undefined,
        // The SDK's defaults collect cookies, headers, bodies and query strings: collect none of it (checkout, tokens in URLs).
        // The visitor's id and area are attached explicitly by tagVisitor().
        dataCollection: { userInfo: false, cookies: false, httpHeaders: false, httpBodies: [], urlQueryParams: false },
        // The feedback form is only opened on the error pages (openFeedbackForm); no floating button on the storefront
        integrations: [
            Sentry.browserTracingIntegration(),
            Sentry.feedbackIntegration({ autoInject: false, showName: false, enableScreenshot: false, colorScheme: 'light' }),
        ],
        tracesSampleRate: 0.1,
        replaysSessionSampleRate: 0,
        replaysOnErrorSampleRate: 1.0,
        ignoreErrors: ['ResizeObserver loop limit exceeded', 'ResizeObserver loop completed with undelivered notifications.'],
        denyUrls: [/^chrome-extension:\/\//i, /^moz-extension:\/\//i, /^safari-(web-)?extension:\/\//i],
    });

    tagVisitor(auth);
    startReplayIfConsented(config);

    router.on('navigate', (event) => {
        tagVisitor(event.detail.page.props.auth as AuthProps | undefined);
        startReplayIfConsented(event.detail.page.props.sentry as SentryConfig | undefined);
    });

    // A request that failed before a page came back (network down, server crashed mid-response)
    router.on('exception', (event) => {
        Sentry.captureException(event.detail.exception);
    });

    // The server answered with something that is not an Inertia page, typically a plain 500
    router.on('invalid', (event) => {
        const response = event.detail.response;

        Sentry.captureMessage('Inertia received a non-Inertia response', {
            level: 'warning',
            tags: { status: String(response.status) },
            extra: { url: String(response.config?.url ?? '').split('?')[0] },
        });
    });
}

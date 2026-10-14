import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import useTranslation from '@/hooks/useTranslation';
import { type SettingHistoryEntry } from '@/types';
import { ArrowRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { formatValue } from './values';

type Status = 'loading' | 'ready' | 'error';

/** Last changes of a group, loaded when the tab is opened (GET admin.settings.history). */
export function HistoryPanel({ groupKey }: { groupKey: string }) {
    const { t, locale } = useTranslation();
    const [status, setStatus] = useState<Status>('loading');
    const [entries, setEntries] = useState<SettingHistoryEntry[]>([]);
    const [attempt, setAttempt] = useState(0);

    const dateFormat = useMemo(() => {
        try {
            return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' });
        } catch {
            return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' });
        }
    }, [locale]);

    useEffect(() => {
        const controller = new AbortController();
        setStatus('loading');

        fetch(`/admin/settings/${encodeURIComponent(groupKey)}/history`, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) throw new Error(`HTTP ${response.status}`);

                return response.json() as Promise<{ data: SettingHistoryEntry[] }>;
            })
            .then((json) => {
                setEntries(json.data);
                setStatus('ready');
            })
            .catch((error: Error) => {
                if (error.name !== 'AbortError') setStatus('error');
            });

        return () => controller.abort();
    }, [groupKey, attempt]);

    if (status === 'loading') {
        return (
            <div role="status" aria-live="polite" className="space-y-2">
                <span className="sr-only">{t('settings.ui.history_loading')}</span>
                <Skeleton className="h-9 w-full" />
                <Skeleton className="h-9 w-full" />
                <Skeleton className="h-9 w-full" />
            </div>
        );
    }

    if (status === 'error') {
        return (
            <div role="alert" className="flex flex-wrap items-center gap-3 text-sm">
                <p className="text-destructive">{t('settings.ui.history_error')}</p>
                <Button type="button" variant="outline" size="sm" onClick={() => setAttempt((n) => n + 1)}>
                    {t('settings.ui.retry')}
                </Button>
            </div>
        );
    }

    if (entries.length === 0) {
        return <p className="py-4 text-sm text-muted-foreground">{t('settings.ui.history_empty')}</p>;
    }

    return (
        <ul className="divide-y" aria-label={t('settings.ui.history')}>
            {entries.map((entry) => (
                <li key={entry.id} className="space-y-1 py-3 first:pt-0 last:pb-0">
                    <p className="text-sm font-medium">{entry.label}</p>
                    <p className="text-sm break-words">
                        <span className="sr-only">{t('settings.ui.old_value')}: </span>
                        <span className="text-muted-foreground">{formatValue(entry.old, t)}</span>
                        <ArrowRight className="mx-1.5 inline size-3.5 align-text-bottom" aria-hidden="true" />
                        <span className="sr-only">{t('settings.ui.new_value')}: </span>
                        <span>{formatValue(entry.new, t)}</span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        <time dateTime={entry.created_at ?? undefined}>{entry.created_at ? dateFormat.format(new Date(entry.created_at)) : '—'}</time>
                        {' · '}
                        <span className="sr-only">{t('settings.ui.changed_by')}: </span>
                        {entry.user?.name ?? t('settings.ui.unknown_user')}
                    </p>
                </li>
            ))}
        </ul>
    );
}

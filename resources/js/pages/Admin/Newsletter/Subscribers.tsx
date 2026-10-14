import NewsletterTabs from '@/components/admin/newsletter/NewsletterTabs';
import { ConfirmDialog } from '@/components/admin/settings/ConfirmDialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTranslation from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { Head, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Download, Search, Trash2, UserMinus } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

type Status = 'pending' | 'confirmed' | 'unsubscribed';

interface SubscriberRow {
    id: number;
    email: string;
    status: Status;
    source: string;
    locale: string | null;
    confirmed_at: string | null;
    created_at: string | null;
}

interface SubscribersProps {
    subscribers: {
        data: SubscriberRow[];
        meta: { total: number; per_page: number; current_page: number; last_page: number; from: number | null; to: number | null };
    };
    counts: Record<Status | 'total', number>;
    filters: { search: string; status: Status | '' };
}

const ALL = 'all';

const STATUS_VARIANT: Record<Status, 'default' | 'secondary' | 'outline'> = {
    confirmed: 'default',
    pending: 'secondary',
    unsubscribed: 'outline',
};

export default function Subscribers({ subscribers, counts, filters }: SubscribersProps) {
    const { t, locale } = useTranslation();
    const [search, setSearch] = useState(filters.search);
    const [pending, setPending] = useState<{ action: 'unsubscribe' | 'erase'; row: SubscriberRow } | null>(null);
    const [busy, setBusy] = useState(false);

    const load = (params: { search?: string; status?: string; page?: number }) => {
        const query = { search: filters.search, status: filters.status, ...params };
        router.get(
            route('admin.newsletter.subscribers'),
            Object.fromEntries(Object.entries(query).filter(([, value]) => value !== '' && value !== undefined && value !== 1)),
            { preserveState: true, preserveScroll: true },
        );
    };

    const submitSearch: FormEventHandler = (e) => {
        e.preventDefault();
        load({ search: search.trim(), page: undefined });
    };

    const confirmAction = () => {
        if (!pending) return;

        const options = {
            preserveScroll: true,
            onStart: () => setBusy(true),
            onFinish: () => {
                setBusy(false);
                setPending(null);
            },
        };

        if (pending.action === 'unsubscribe') {
            router.post(route('admin.newsletter.subscribers.unsubscribe', pending.row.id), {}, options);
        } else {
            router.delete(route('admin.newsletter.subscribers.destroy', pending.row.id), options);
        }
    };

    const formatDate = (value: string | null) => (value ? new Date(value).toLocaleDateString(locale, { dateStyle: 'medium' }) : '–');

    const { meta } = subscribers;

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: t('newsletter.admin.title'), href: route('admin.newsletter.subscribers') },
            ]}
        >
            <Head title={t('newsletter.admin.title')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('newsletter.admin.title')}</h1>
                        <p className="text-muted-foreground">{t('newsletter.admin.description')}</p>
                    </div>
                    <Button asChild variant="outline">
                        <a href={route('admin.newsletter.subscribers.export')}>
                            <Download className="mr-2 h-4 w-4" />
                            {t('newsletter.admin.export')}
                        </a>
                    </Button>
                </div>

                <NewsletterTabs active="subscribers" />

                <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
                    {(['confirmed', 'pending', 'unsubscribed', 'total'] as const).map((key) => (
                        <Card key={key}>
                            <CardContent className="p-4">
                                <p className="text-sm text-muted-foreground">{t(`newsletter.admin.count.${key}`)}</p>
                                <p className="text-2xl font-bold tabular-nums">{counts[key]}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <div className="flex flex-col gap-3 sm:flex-row">
                    <form onSubmit={submitSearch} className="flex flex-1 gap-2">
                        <label htmlFor="subscriber-search" className="sr-only">
                            {t('newsletter.admin.search_placeholder')}
                        </label>
                        <Input
                            id="subscriber-search"
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={t('newsletter.admin.search_placeholder')}
                        />
                        <Button type="submit" variant="secondary">
                            <Search className="h-4 w-4" />
                            <span className="sr-only sm:not-sr-only sm:ml-2">{t('newsletter.admin.search')}</span>
                        </Button>
                    </form>
                    <Select value={filters.status || ALL} onValueChange={(value) => load({ status: value === ALL ? '' : value, page: undefined })}>
                        <SelectTrigger className="sm:w-56" aria-label={t('newsletter.admin.col.status')}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value={ALL}>{t('newsletter.admin.status_all')}</SelectItem>
                            {(['confirmed', 'pending', 'unsubscribed'] as const).map((status) => (
                                <SelectItem key={status} value={status}>
                                    {t(`newsletter.admin.status.${status}`)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-4 font-medium">{t('newsletter.admin.col.email')}</th>
                                    <th className="p-4 font-medium">{t('newsletter.admin.col.status')}</th>
                                    <th className="hidden p-4 font-medium md:table-cell">{t('newsletter.admin.col.source')}</th>
                                    <th className="hidden p-4 font-medium md:table-cell">{t('newsletter.admin.col.locale')}</th>
                                    <th className="hidden p-4 font-medium lg:table-cell">{t('newsletter.admin.col.confirmed_at')}</th>
                                    <th className="p-4 text-right font-medium">{t('newsletter.admin.col.actions')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {subscribers.data.length === 0 && (
                                    <tr>
                                        <td colSpan={6} className="p-8 text-center text-muted-foreground">
                                            {t('newsletter.admin.empty')}
                                        </td>
                                    </tr>
                                )}
                                {subscribers.data.map((row) => (
                                    <tr key={row.id} className="border-b hover:bg-muted/50">
                                        <td className="max-w-[16rem] truncate p-4 font-medium" title={row.email}>
                                            {row.email}
                                        </td>
                                        <td className="p-4">
                                            <Badge variant={STATUS_VARIANT[row.status]}>{t(`newsletter.admin.status.${row.status}`)}</Badge>
                                        </td>
                                        <td className="hidden p-4 md:table-cell">{t(`newsletter.admin.source.${row.source}`)}</td>
                                        <td className="hidden p-4 uppercase md:table-cell">{row.locale ?? '–'}</td>
                                        <td className="hidden p-4 tabular-nums lg:table-cell">{formatDate(row.confirmed_at)}</td>
                                        <td className="p-4">
                                            <div className="flex justify-end gap-1">
                                                {row.status !== 'unsubscribed' && (
                                                    <Button variant="ghost" size="sm" onClick={() => setPending({ action: 'unsubscribe', row })}>
                                                        <UserMinus className="h-4 w-4" />
                                                        <span className="sr-only lg:not-sr-only lg:ml-1">{t('newsletter.admin.unsubscribe')}</span>
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="text-destructive hover:text-destructive"
                                                    onClick={() => setPending({ action: 'erase', row })}
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                    <span className="sr-only lg:not-sr-only lg:ml-1">{t('newsletter.admin.erase')}</span>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>

                    {meta.last_page > 1 && (
                        <div className="flex items-center justify-between gap-4 p-4">
                            <p className="text-sm text-muted-foreground">
                                {t('newsletter.admin.page_info', { current: meta.current_page, last: meta.last_page })}
                            </p>
                            <div className="flex gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page <= 1}
                                    onClick={() => load({ page: meta.current_page - 1 })}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                    {t('newsletter.admin.previous')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => load({ page: meta.current_page + 1 })}
                                >
                                    {t('newsletter.admin.next')}
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            </div>

            <ConfirmDialog
                open={pending !== null}
                title={pending?.action === 'erase' ? t('newsletter.admin.erase_title') : t('newsletter.admin.unsubscribe_title')}
                description={
                    pending
                        ? t(pending.action === 'erase' ? 'newsletter.admin.erase_body' : 'newsletter.admin.unsubscribe_body', {
                              email: pending.row.email,
                          })
                        : ''
                }
                confirmLabel={pending?.action === 'erase' ? t('newsletter.admin.erase') : t('newsletter.admin.unsubscribe')}
                destructive
                busy={busy}
                onConfirm={confirmAction}
                onCancel={() => setPending(null)}
            />
        </AdminLayout>
    );
}

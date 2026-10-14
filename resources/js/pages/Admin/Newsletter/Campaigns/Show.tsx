import CampaignStatusBadge from '@/components/admin/newsletter/CampaignStatusBadge';
import NewsletterTabs from '@/components/admin/newsletter/NewsletterTabs';
import { ConfirmDialog } from '@/components/admin/settings/ConfirmDialog';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { Ban, Pencil } from 'lucide-react';
import { useEffect, useState } from 'react';
import { type CampaignSummary } from './Index';

interface ShowProps {
    campaign: CampaignSummary & { preheader: string | null; body_markdown: string; editable: boolean };
    stats: { recipients: number; sent: number; failed: number; skipped: number; pending: number };
}

export default function Show({ campaign, stats }: ShowProps) {
    const { t, locale } = useTranslation();
    const [confirmCancel, setConfirmCancel] = useState(false);
    const [busy, setBusy] = useState(false);

    // Follow the progress while the queue is sending
    useEffect(() => {
        if (campaign.status !== 'sending') return;
        const timer = window.setInterval(() => router.reload({ only: ['campaign', 'stats'] }), 5000);
        return () => window.clearInterval(timer);
    }, [campaign.status]);

    const formatDate = (value: string | null) => (value ? new Date(value).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' }) : '–');

    const cancel = () => {
        router.post(
            route('admin.newsletter.campaigns.cancel', campaign.id),
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => {
                    setBusy(false);
                    setConfirmCancel(false);
                },
            },
        );
    };

    const cancellable = campaign.status === 'scheduled' || campaign.status === 'sending';

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: t('newsletter.admin.title'), href: route('admin.newsletter.subscribers') },
                { title: t('newsletter.admin.tabs.campaigns'), href: route('admin.newsletter.campaigns.index') },
                { title: campaign.subject, href: route('admin.newsletter.campaigns.show', campaign.id) },
            ]}
        >
            <Head title={campaign.subject} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                    <div className="min-w-0 space-y-2">
                        <h1 className="text-2xl font-bold tracking-tight break-words md:text-3xl">{campaign.subject}</h1>
                        <div className="flex flex-wrap items-center gap-3 text-sm text-muted-foreground">
                            <CampaignStatusBadge status={campaign.status} />
                            <span>{campaign.locale ? campaign.locale.toUpperCase() : t('newsletter.admin.campaign.locale_all')}</span>
                            {campaign.status === 'scheduled' && (
                                <span>
                                    {t('newsletter.admin.campaign.schedule_at')}: {formatDate(campaign.scheduled_at)}
                                </span>
                            )}
                            {campaign.started_at && (
                                <span>
                                    {t('newsletter.admin.campaign.started')}: {formatDate(campaign.started_at)}
                                </span>
                            )}
                            {campaign.finished_at && (
                                <span>
                                    {t('newsletter.admin.campaign.finished')}: {formatDate(campaign.finished_at)}
                                </span>
                            )}
                        </div>
                    </div>
                    <div className="flex gap-2">
                        {campaign.editable && (
                            <Button asChild variant="outline">
                                <Link href={route('admin.newsletter.campaigns.edit', campaign.id)}>
                                    <Pencil className="h-4 w-4" />
                                    {t('newsletter.admin.campaign.edit')}
                                </Link>
                            </Button>
                        )}
                        {cancellable && (
                            <Button variant="destructive" onClick={() => setConfirmCancel(true)}>
                                <Ban className="h-4 w-4" />
                                {t('newsletter.admin.campaign.cancel')}
                            </Button>
                        )}
                    </div>
                </div>

                <NewsletterTabs active="campaigns" />

                <div className="grid grid-cols-2 gap-4 md:grid-cols-5">
                    {(['recipients', 'sent', 'pending', 'skipped', 'failed'] as const).map((key) => (
                        <Card key={key}>
                            <CardContent className="p-4">
                                <p className="text-sm text-muted-foreground">{t(`newsletter.admin.campaign.stats.${key}`)}</p>
                                <p className="text-2xl font-bold tabular-nums">{stats[key]}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">{t('newsletter.admin.campaign.body')}</CardTitle>
                        {campaign.preheader && <p className="text-sm text-muted-foreground">{campaign.preheader}</p>}
                    </CardHeader>
                    <CardContent>
                        <pre className="max-h-[480px] overflow-auto rounded-md bg-muted p-4 text-sm whitespace-pre-wrap">
                            {campaign.body_markdown}
                        </pre>
                    </CardContent>
                </Card>
            </div>

            <ConfirmDialog
                open={confirmCancel}
                title={t('newsletter.admin.campaign.confirm_cancel_title')}
                description={t('newsletter.admin.campaign.confirm_cancel_body')}
                confirmLabel={t('newsletter.admin.campaign.cancel')}
                destructive
                busy={busy}
                onConfirm={cancel}
                onCancel={() => setConfirmCancel(false)}
            />
        </AdminLayout>
    );
}

import CampaignStatusBadge, { type CampaignStatus } from '@/components/admin/newsletter/CampaignStatusBadge';
import NewsletterTabs from '@/components/admin/newsletter/NewsletterTabs';
import { Button } from '@/components/ui/button';
import { Card } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Plus } from 'lucide-react';

export interface CampaignSummary {
    id: number;
    subject: string;
    locale: string | null;
    status: CampaignStatus;
    scheduled_at: string | null;
    started_at: string | null;
    finished_at: string | null;
    recipients_count: number;
    sent_count: number;
    failed_count: number;
    created_at: string | null;
}

interface IndexProps {
    campaigns: { data: CampaignSummary[]; meta: { total: number; current_page: number; last_page: number } };
}

export default function Index({ campaigns }: IndexProps) {
    const { t, locale } = useTranslation();
    const { meta } = campaigns;

    const formatDate = (value: string | null) => (value ? new Date(value).toLocaleString(locale, { dateStyle: 'medium', timeStyle: 'short' }) : '–');

    const linkFor = (campaign: CampaignSummary) =>
        campaign.status === 'draft' || campaign.status === 'scheduled'
            ? route('admin.newsletter.campaigns.edit', campaign.id)
            : route('admin.newsletter.campaigns.show', campaign.id);

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: t('newsletter.admin.title'), href: route('admin.newsletter.subscribers') },
                { title: t('newsletter.admin.tabs.campaigns'), href: route('admin.newsletter.campaigns.index') },
            ]}
        >
            <Head title={t('newsletter.admin.tabs.campaigns')} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('newsletter.admin.title')}</h1>
                        <p className="text-muted-foreground">{t('newsletter.admin.description')}</p>
                    </div>
                    <Button asChild>
                        <Link href={route('admin.newsletter.campaigns.create')}>
                            <Plus className="mr-2 h-4 w-4" />
                            {t('newsletter.admin.campaign.new')}
                        </Link>
                    </Button>
                </div>

                <NewsletterTabs active="campaigns" />

                <Card>
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b text-left">
                                    <th className="p-4 font-medium">{t('newsletter.admin.campaign.col.subject')}</th>
                                    <th className="p-4 font-medium">{t('newsletter.admin.campaign.col.status')}</th>
                                    <th className="hidden p-4 font-medium md:table-cell">{t('newsletter.admin.campaign.col.audience')}</th>
                                    <th className="hidden p-4 text-right font-medium md:table-cell">{t('newsletter.admin.campaign.col.sent')}</th>
                                    <th className="hidden p-4 font-medium lg:table-cell">{t('newsletter.admin.campaign.col.date')}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {campaigns.data.length === 0 && (
                                    <tr>
                                        <td colSpan={5} className="p-8 text-center text-muted-foreground">
                                            {t('newsletter.admin.campaign.empty')}
                                        </td>
                                    </tr>
                                )}
                                {campaigns.data.map((campaign) => (
                                    <tr key={campaign.id} className="border-b hover:bg-muted/50">
                                        <td className="max-w-[20rem] truncate p-4 font-medium">
                                            <Link href={linkFor(campaign)} className="hover:underline">
                                                {campaign.subject}
                                            </Link>
                                        </td>
                                        <td className="p-4">
                                            <CampaignStatusBadge status={campaign.status} />
                                        </td>
                                        <td className="hidden p-4 md:table-cell">
                                            {campaign.locale ? campaign.locale.toUpperCase() : t('newsletter.admin.campaign.locale_all')}
                                        </td>
                                        <td className="hidden p-4 text-right tabular-nums md:table-cell">
                                            {campaign.status === 'draft' ? '–' : `${campaign.sent_count} / ${campaign.recipients_count}`}
                                        </td>
                                        <td className="hidden p-4 tabular-nums lg:table-cell">
                                            {formatDate(campaign.finished_at ?? campaign.started_at ?? campaign.scheduled_at ?? campaign.created_at)}
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
                                    onClick={() => router.get(route('admin.newsletter.campaigns.index'), { page: meta.current_page - 1 })}
                                >
                                    <ChevronLeft className="h-4 w-4" />
                                    {t('newsletter.admin.previous')}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    disabled={meta.current_page >= meta.last_page}
                                    onClick={() => router.get(route('admin.newsletter.campaigns.index'), { page: meta.current_page + 1 })}
                                >
                                    {t('newsletter.admin.next')}
                                    <ChevronRight className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    )}
                </Card>
            </div>
        </AdminLayout>
    );
}

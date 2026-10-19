import CampaignStatusBadge from '@/components/admin/newsletter/CampaignStatusBadge';
import NewsletterTabs from '@/components/admin/newsletter/NewsletterTabs';
import { ConfirmDialog } from '@/components/admin/settings/ConfirmDialog';
import InputError from '@/components/input-error';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import useTranslation from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { CalendarClock, LoaderCircle, Save, Send, TestTube2 } from 'lucide-react';
import { type FormEventHandler, useEffect, useState } from 'react';
import { type CampaignSummary } from './Index';

interface EditProps {
    campaign: (CampaignSummary & { preheader: string | null; body_markdown: string }) | null;
    recipientCounts: Record<string, number>;
    locales: string[];
    newsletterEnabled: boolean;
    adminEmail: string;
}

const ALL = 'all';

/** Value for <input type="datetime-local">, in the browser's time zone. */
function toLocalInput(iso: string | null): string {
    if (!iso) return '';
    const date = new Date(iso);
    const pad = (n: number) => String(n).padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export default function Edit({ campaign, recipientCounts, locales, newsletterEnabled, adminEmail }: EditProps) {
    const { t, locale: uiLocale } = useTranslation();
    const { errors: pageErrors } = usePage<{ errors: Record<string, string> }>().props;
    const { data, setData, post, put, processing, errors, isDirty } = useForm({
        subject: campaign?.subject ?? '',
        preheader: campaign?.preheader ?? '',
        body_markdown: campaign?.body_markdown ?? '',
        locale: campaign?.locale ?? '',
    });
    const [previewHtml, setPreviewHtml] = useState('');
    const [previewLoading, setPreviewLoading] = useState(false);
    const [scheduleAt, setScheduleAt] = useState(toLocalInput(campaign?.scheduled_at ?? null));
    const [confirm, setConfirm] = useState<'send' | 'schedule' | null>(null);
    const [busy, setBusy] = useState(false);

    const recipients = recipientCounts[data.locale || ALL] ?? 0;
    const canSend = campaign !== null && !isDirty && newsletterEnabled;

    // Live preview through the server's email renderer (debounced)
    useEffect(() => {
        if (data.subject.trim() === '' || data.body_markdown.trim() === '') {
            setPreviewHtml('');
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setPreviewLoading(true);
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
                const response = await fetch(route('admin.newsletter.campaigns.preview'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify(data),
                    signal: controller.signal,
                });
                if (response.ok) {
                    setPreviewHtml(((await response.json()) as { html: string }).html);
                }
            } catch {
                // aborted or offline: keep the last preview
            } finally {
                setPreviewLoading(false);
            }
        }, 600);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [data]);

    const save: FormEventHandler = (e) => {
        e.preventDefault();
        if (campaign) {
            put(route('admin.newsletter.campaigns.update', campaign.id), { preserveScroll: true });
        } else {
            post(route('admin.newsletter.campaigns.store'));
        }
    };

    const sendTest = () => {
        if (!campaign) return;
        router.post(route('admin.newsletter.campaigns.test', campaign.id), {}, { preserveScroll: true });
    };

    const confirmAction = () => {
        if (!campaign || !confirm) return;
        const options = {
            onStart: () => setBusy(true),
            onFinish: () => {
                setBusy(false);
                setConfirm(null);
            },
        };
        if (confirm === 'send') {
            router.post(route('admin.newsletter.campaigns.send', campaign.id), {}, options);
        } else {
            router.post(route('admin.newsletter.campaigns.schedule', campaign.id), { scheduled_at: new Date(scheduleAt).toISOString() }, options);
        }
    };

    const title = campaign ? t('newsletter.admin.campaign.edit_title') : t('newsletter.admin.campaign.new');

    return (
        <AdminLayout
            breadcrumbs={[
                { title: t('admin.nav.admin'), href: '/admin/dashboard' },
                { title: t('newsletter.admin.title'), href: route('admin.newsletter.subscribers') },
                { title: t('newsletter.admin.tabs.campaigns'), href: route('admin.newsletter.campaigns.index') },
                { title, href: campaign ? route('admin.newsletter.campaigns.edit', campaign.id) : route('admin.newsletter.campaigns.create') },
            ]}
        >
            <Head title={title} />

            <div className="space-y-6 p-4 md:p-6">
                <div className="flex flex-wrap items-center gap-3">
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{title}</h1>
                    {campaign && <CampaignStatusBadge status={campaign.status} />}
                    {campaign?.status === 'scheduled' && (
                        <Link href={route('admin.newsletter.campaigns.show', campaign.id)} className="text-sm underline">
                            {t('newsletter.admin.campaign.view')}
                        </Link>
                    )}
                </div>

                <NewsletterTabs active="campaigns" />

                {!newsletterEnabled && (
                    <Alert>
                        <AlertDescription>{t('newsletter.admin.campaign.newsletter_off')}</AlertDescription>
                    </Alert>
                )}
                {pageErrors.campaign && (
                    <Alert variant="destructive">
                        <AlertDescription>{pageErrors.campaign}</AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-6 xl:grid-cols-2">
                    <Card>
                        <CardContent className="pt-6">
                            <form onSubmit={save} className="space-y-5">
                                <div className="grid gap-2">
                                    <Label htmlFor="subject">{t('newsletter.admin.campaign.subject')}</Label>
                                    <Input
                                        id="subject"
                                        value={data.subject}
                                        onChange={(e) => setData('subject', e.target.value)}
                                        maxLength={255}
                                        required
                                    />
                                    <InputError message={errors.subject} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="preheader">{t('newsletter.admin.campaign.preheader')}</Label>
                                    <Input
                                        id="preheader"
                                        value={data.preheader}
                                        onChange={(e) => setData('preheader', e.target.value)}
                                        maxLength={255}
                                    />
                                    <p className="text-sm text-muted-foreground">{t('newsletter.admin.campaign.preheader_help')}</p>
                                    <InputError message={errors.preheader} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="locale">{t('newsletter.admin.campaign.locale')}</Label>
                                    <Select value={data.locale || ALL} onValueChange={(value) => setData('locale', value === ALL ? '' : value)}>
                                        <SelectTrigger id="locale">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value={ALL}>
                                                {t('newsletter.admin.campaign.locale_all')} ({recipientCounts[ALL] ?? 0})
                                            </SelectItem>
                                            {locales.map((code) => (
                                                <SelectItem key={code} value={code}>
                                                    {code.toUpperCase()} ({recipientCounts[code] ?? 0})
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.locale} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="body_markdown">{t('newsletter.admin.campaign.body')}</Label>
                                    <Textarea
                                        id="body_markdown"
                                        value={data.body_markdown}
                                        onChange={(e) => setData('body_markdown', e.target.value)}
                                        rows={16}
                                        className="font-mono text-sm"
                                        required
                                    />
                                    <p className="text-sm text-muted-foreground">{t('newsletter.admin.campaign.body_help')}</p>
                                    <InputError message={errors.body_markdown} />
                                </div>

                                <Button type="submit" disabled={processing || (campaign !== null && !isDirty)}>
                                    {processing ? <LoaderCircle className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />}
                                    {t('newsletter.admin.campaign.save')}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>

                    <Card className="flex flex-col">
                        <CardHeader className="flex-row items-center justify-between space-y-0">
                            <CardTitle className="text-base">{t('newsletter.admin.campaign.preview')}</CardTitle>
                            {previewLoading && <LoaderCircle className="h-4 w-4 animate-spin text-muted-foreground" aria-hidden="true" />}
                        </CardHeader>
                        <CardContent className="flex-1">
                            <iframe
                                title={t('newsletter.admin.campaign.preview')}
                                srcDoc={previewHtml}
                                sandbox=""
                                className="h-[640px] w-full rounded-md border bg-white"
                            />
                        </CardContent>
                    </Card>
                </div>

                {campaign && (
                    <Card>
                        <CardContent className="space-y-4 pt-6">
                            {isDirty && <p className="text-sm text-amber-700">{t('newsletter.admin.campaign.save_first')}</p>}

                            <div className="flex flex-wrap items-end gap-3">
                                <Button variant="outline" onClick={sendTest} disabled={isDirty}>
                                    <TestTube2 className="h-4 w-4" />
                                    {t('newsletter.admin.campaign.send_test')}
                                </Button>
                                <span className="text-sm text-muted-foreground">{adminEmail}</span>
                            </div>

                            <div className="flex flex-wrap items-end gap-3 border-t pt-4">
                                <Button onClick={() => setConfirm('send')} disabled={!canSend || recipients === 0}>
                                    <Send className="h-4 w-4" />
                                    {t('newsletter.admin.campaign.send_now')}
                                </Button>

                                <div className="grid gap-1">
                                    <Label htmlFor="scheduled_at" className="text-sm">
                                        {t('newsletter.admin.campaign.schedule_at')}
                                    </Label>
                                    <Input
                                        id="scheduled_at"
                                        type="datetime-local"
                                        value={scheduleAt}
                                        onChange={(e) => setScheduleAt(e.target.value)}
                                        className="w-auto"
                                    />
                                </div>
                                <Button variant="secondary" onClick={() => setConfirm('schedule')} disabled={!canSend || scheduleAt === ''}>
                                    <CalendarClock className="h-4 w-4" />
                                    {t('newsletter.admin.campaign.schedule')}
                                </Button>
                            </div>
                            <InputError message={pageErrors.scheduled_at} />
                        </CardContent>
                    </Card>
                )}
            </div>

            <ConfirmDialog
                open={confirm !== null}
                title={
                    confirm === 'schedule' ? t('newsletter.admin.campaign.confirm_schedule_title') : t('newsletter.admin.campaign.confirm_send_title')
                }
                description={
                    confirm === 'schedule'
                        ? t('newsletter.admin.campaign.confirm_schedule_body', {
                              date: scheduleAt ? new Date(scheduleAt).toLocaleString(uiLocale, { dateStyle: 'medium', timeStyle: 'short' }) : '',
                              count: recipients,
                          })
                        : t('newsletter.admin.campaign.confirm_send_body', { count: recipients })
                }
                confirmLabel={confirm === 'schedule' ? t('newsletter.admin.campaign.schedule') : t('newsletter.admin.campaign.send_now')}
                busy={busy}
                onConfirm={confirmAction}
                onCancel={() => setConfirm(null)}
            />
        </AdminLayout>
    );
}

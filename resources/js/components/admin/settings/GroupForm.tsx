import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import useTranslation from '@/hooks/useTranslation';
import { type SettingField, type SettingGroup, type SettingsPageProps } from '@/types';
import { Link, router } from '@inertiajs/react';
import { AlertCircle, ExternalLink } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState, type FormEvent } from 'react';
import { ConfirmDialog } from './ConfirmDialog';
import { COUNTRY_CODES } from './countries';
import { groupIcon } from './group-icon';
import { restoreGroupHash } from './hash';
import { HistoryPanel } from './HistoryPanel';
import { SettingFieldRow } from './SettingFieldRow';
import {
    draftFromGroup,
    formatValue,
    groupErrors,
    isFieldDirty,
    toDraft,
    toPayload,
    type DraftValue,
    type GroupDraft,
    type RowDraft,
} from './values';

// Extra links shown under a group (the box sizes and their shipping costs have their own admin page).
const GROUP_LINKS: Record<string, { href: string; labelKey: string }> = {
    shipping: { href: '/admin/categories-sizes', labelKey: 'settings.ui.sizes_link' },
};

interface GroupFormProps {
    group: SettingGroup;
    onDirtyChange: (dirty: boolean) => void;
}

const freshGroup = (page: { props: unknown }, key: string): SettingGroup | undefined =>
    (page.props as SettingsPageProps).groups.find((candidate) => candidate.key === key);

/** The editor of one group: generated rows, one save per group, per-field reset, confirm for risky changes, history tab. */
export function GroupForm({ group, onDirtyChange }: GroupFormProps) {
    const { t } = useTranslation();
    const [draft, setDraft] = useState<GroupDraft>(() => draftFromGroup(group));
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [processing, setProcessing] = useState(false);
    const [tab, setTab] = useState<'values' | 'history'>('values');
    const [confirmSave, setConfirmSave] = useState(false);
    const [resetTarget, setResetTarget] = useState<SettingField | null>(null);

    const fieldsByKey = useMemo(() => new Map(group.fields.map((field) => [field.key, field])), [group.fields]);
    const dirtyFields = useMemo(
        () => group.fields.filter((field) => !field.readonly && isFieldDirty(field, draft[field.name])),
        [group.fields, draft],
    );
    const dirty = dirtyFields.length > 0;
    const riskyFields = dirtyFields.filter((field) => field.risk === 'high');
    const errorsByField = useMemo(() => groupErrors(errors), [errors]);
    const errorCount = Object.keys(errors).length;
    const Icon = groupIcon(group.icon);
    const link = GROUP_LINKS[group.key];

    useEffect(() => {
        onDirtyChange(dirty);
    }, [dirty, onDirtyChange]);

    // leaving the page with unsaved changes (reload, closing the tab)
    useEffect(() => {
        if (!dirty) return;

        const warn = (event: BeforeUnloadEvent) => event.preventDefault();
        window.addEventListener('beforeunload', warn);

        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    // server errors of a field go away as soon as the field is edited
    const clearErrors = useCallback((name: string) => {
        setErrors((previous) => {
            const remaining = Object.fromEntries(Object.entries(previous).filter(([key]) => key !== name && !key.startsWith(`${name}.`)));

            return Object.keys(remaining).length === Object.keys(previous).length ? previous : remaining;
        });
    }, []);

    const handleChange = useCallback(
        (name: string, value: DraftValue) => {
            setDraft((previous) => ({ ...previous, [name]: value }));
            clearErrors(name);
        },
        [clearErrors],
    );

    const handleResetRequest = useCallback((field: SettingField) => setResetTarget(field), []);

    const save = () => {
        const payload: Record<string, unknown> = {};
        dirtyFields.forEach((field) => (payload[field.name] = toPayload(field, draft[field.name])));

        setConfirmSave(false);
        setProcessing(true);
        router.patch(`/admin/settings/${group.key}`, payload as Parameters<typeof router.patch>[1], {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                setErrors({});
                const fresh = freshGroup(page, group.key);
                if (fresh) setDraft(draftFromGroup(fresh));
            },
            onError: (serverErrors) => setErrors(serverErrors),
            onFinish: () => {
                setProcessing(false);
                restoreGroupHash(group.key);
            },
        });
    };

    const handleSubmit = (event: FormEvent) => {
        event.preventDefault();
        if (!dirty || processing) return;

        if (riskyFields.length > 0) {
            setConfirmSave(true);
        } else {
            save();
        }
    };

    const discard = () => {
        setDraft(draftFromGroup(group));
        setErrors({});
    };

    const reset = () => {
        const target = resetTarget;
        if (!target) return;

        setProcessing(true);
        router.delete(`/admin/settings/${target.key}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: (page) => {
                const fresh = freshGroup(page, group.key)?.fields.find((field) => field.key === target.key);
                if (fresh) setDraft((previous) => ({ ...previous, [target.name]: toDraft(fresh) }));
                clearErrors(target.name);
            },
            onFinish: () => {
                setProcessing(false);
                setResetTarget(null);
                restoreGroupHash(group.key);
            },
        });
    };

    return (
        <Card>
            <Tabs value={tab} onValueChange={(next) => setTab(next as 'values' | 'history')}>
                <CardHeader className="gap-3 px-4 sm:px-6">
                    <div className="flex items-start gap-3">
                        <span className="flex size-9 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                            <Icon className="size-5" aria-hidden="true" />
                        </span>
                        <div className="min-w-0 space-y-1">
                            <CardTitle className="text-lg" role="heading" aria-level={2}>
                                {group.label}
                            </CardTitle>
                            <CardDescription>{group.description}</CardDescription>
                        </div>
                    </div>
                    <TabsList role="tablist" className="w-fit">
                        <TabsTrigger type="button" role="tab" value="values" aria-selected={tab === 'values'}>
                            {t('settings.ui.tab_settings')}
                        </TabsTrigger>
                        <TabsTrigger type="button" role="tab" value="history" aria-selected={tab === 'history'}>
                            {t('settings.ui.history')}
                        </TabsTrigger>
                    </TabsList>
                </CardHeader>

                <TabsContent value="values" className="mt-4">
                    <form onSubmit={handleSubmit} noValidate className="pb-20 md:pb-0">
                        <CardContent className="space-y-4 px-4 sm:px-6">
                            {errorCount > 0 && (
                                <Alert variant="destructive">
                                    <AlertCircle aria-hidden="true" />
                                    <AlertDescription>{t('settings.ui.validation_summary', { count: errorCount })}</AlertDescription>
                                </Alert>
                            )}

                            <div className="divide-y">
                                {group.fields.map((field) => {
                                    const controller = field.dependsOn ? fieldsByKey.get(field.dependsOn) : undefined;
                                    const source = field.memberOf ? fieldsByKey.get(field.memberOf) : undefined;
                                    // json rows (shipping.carriers): their codes are the options, their names the labels
                                    const rows = source?.type === 'json' ? (draft[source.name] as RowDraft[]).filter((row) => row.code) : undefined;
                                    const options = source
                                        ? rows
                                            ? rows.map((row) => String(row.code))
                                            : (draft[source.name] as string[])
                                        : field.type === 'countries'
                                          ? COUNTRY_CODES
                                          : field.options;

                                    return (
                                        <SettingFieldRow
                                            key={field.key}
                                            field={field}
                                            value={draft[field.name]}
                                            options={options}
                                            countryOptions={field.type === 'countries' || source?.type === 'countries'}
                                            optionNames={
                                                rows && Object.fromEntries(rows.map((row) => [String(row.code), String(row.name || row.code)]))
                                            }
                                            errors={errorsByField[field.name]}
                                            disabledBy={controller && !draft[controller.name] ? controller.label : undefined}
                                            busy={processing}
                                            defaultText={formatValue(field.default, t)}
                                            onChange={handleChange}
                                            onReset={handleResetRequest}
                                        />
                                    );
                                })}
                            </div>

                            {link && (
                                <Link
                                    href={link.href}
                                    className="inline-flex items-center gap-1.5 text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    {t(link.labelKey)}
                                    <ExternalLink className="size-3.5" aria-hidden="true" />
                                </Link>
                            )}
                        </CardContent>

                        <div className="fixed inset-x-0 bottom-0 z-30 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:sticky md:inset-x-auto md:z-10 md:mt-6 md:rounded-b-xl md:bg-card/95 md:px-6">
                            <p className="text-sm" aria-live="polite">
                                {dirty ? (
                                    <span className="flex items-center gap-2 font-medium text-amber-700 dark:text-amber-400">
                                        <span className="size-2 rounded-full bg-amber-500" aria-hidden="true" />
                                        {t('settings.ui.unsaved_changes')}
                                    </span>
                                ) : null}
                            </p>
                            <div className="flex gap-2">
                                <Button type="button" variant="outline" onClick={discard} disabled={!dirty || processing}>
                                    {t('settings.ui.discard')}
                                </Button>
                                <Button type="submit" disabled={!dirty || processing}>
                                    {processing ? t('settings.ui.saving') : t('settings.ui.save')}
                                </Button>
                            </div>
                        </div>
                    </form>
                </TabsContent>

                <TabsContent value="history" className="mt-4">
                    <CardContent className="px-4 sm:px-6">
                        <HistoryPanel groupKey={group.key} />
                    </CardContent>
                </TabsContent>
            </Tabs>

            <ConfirmDialog
                open={confirmSave}
                title={t('settings.ui.confirm_title')}
                description={t('settings.ui.confirm_body')}
                confirmLabel={t('settings.ui.confirm')}
                busy={processing}
                onConfirm={save}
                onCancel={() => setConfirmSave(false)}
            >
                <ul className="list-disc space-y-1 pl-5 text-sm">
                    {riskyFields.map((field) => (
                        <li key={field.key}>
                            <span className="font-medium">{field.label}</span>:{' '}
                            <span className="text-muted-foreground">
                                {formatValue(field.value, t)} → {formatValue(toPayload(field, draft[field.name]), t)}
                            </span>
                        </li>
                    ))}
                </ul>
            </ConfirmDialog>

            <ConfirmDialog
                open={resetTarget !== null}
                title={t('settings.ui.reset_confirm_title')}
                description={t('settings.ui.reset_confirm_body')}
                confirmLabel={t('settings.ui.reset_to_default')}
                busy={processing}
                onConfirm={reset}
                onCancel={() => setResetTarget(null)}
            >
                {resetTarget && (
                    <p className="text-sm">
                        <span className="font-medium">{resetTarget.label}</span>:{' '}
                        <span className="text-muted-foreground">
                            {t('settings.ui.default_value', { value: formatValue(resetTarget.default, t) })}
                        </span>
                    </p>
                )}
            </ConfirmDialog>
        </Card>
    );
}

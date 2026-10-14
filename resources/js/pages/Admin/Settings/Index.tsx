// Admin -> Settings. Everything is generated from the catalogue in config/settings.php (docs/admin-settings.md).
import { ConfirmDialog } from '@/components/admin/settings/ConfirmDialog';
import { groupIcon } from '@/components/admin/settings/group-icon';
import { GroupForm } from '@/components/admin/settings/GroupForm';
import { GroupNav } from '@/components/admin/settings/GroupNav';
import { readGroupHash, restoreGroupHash } from '@/components/admin/settings/hash';
import { SystemPanel } from '@/components/admin/settings/SystemPanel';
import { Card, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import AdminLayout from '@/layouts/admin-layout';
import { type SettingsPageProps } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

export default function SettingsIndex({ groups, system }: SettingsPageProps) {
    const { t } = useTranslation();
    const keys = useMemo(() => groups.map((group) => group.key), [groups]);
    const keyList = keys.join('|');

    const [activeKey, setActiveKey] = useState(() => {
        const fromHash = readGroupHash();

        return keys.includes(fromHash) ? fromHash : (keys[0] ?? '');
    });
    const [dirty, setDirty] = useState(false);
    const [pendingKey, setPendingKey] = useState<string | null>(null);
    // an Inertia navigation to another page (sidebar link...) held back while the form has unsaved changes
    const [pendingUrl, setPendingUrl] = useState<string | null>(null);

    // refs, so the hashchange listener always sees the latest state
    const activeRef = useRef(activeKey);
    const dirtyRef = useRef(false);
    const forceRef = useRef(false);
    const forceNavigateRef = useRef(false);

    useEffect(() => {
        activeRef.current = activeKey;
    }, [activeKey]);

    const handleDirtyChange = useCallback((next: boolean) => {
        dirtyRef.current = next;
        setDirty(next);
    }, []);

    // back/forward or a typed #hash: switch group, unless there are unsaved changes (then ask first)
    useEffect(() => {
        const onHashChange = () => {
            const next = readGroupHash();
            if (!keyList.split('|').includes(next) || next === activeRef.current) return;

            if (dirtyRef.current && !forceRef.current) {
                restoreGroupHash(activeRef.current);
                setPendingKey(next);

                return;
            }

            forceRef.current = false;
            dirtyRef.current = false;
            setDirty(false);
            setActiveKey(next);
        };

        window.addEventListener('hashchange', onHashChange);

        return () => window.removeEventListener('hashchange', onHashChange);
    }, [keyList]);

    // Inertia navigation to another page (sidebar link): ask first. Saves, resets and reloads of this page are not navigations.
    useEffect(() => {
        return router.on('before', (event) => {
            const { visit } = event.detail;

            if (!dirtyRef.current || forceNavigateRef.current) return;
            if (visit.method !== 'get' || visit.url.pathname === window.location.pathname) return;

            setPendingUrl(visit.url.href);

            return false;
        });
    }, []);

    const handleSelect = useCallback(
        (key: string) => {
            if (key === activeKey) return;

            if (dirty) {
                setPendingKey(key);
            } else {
                window.location.hash = key;
            }
        },
        [activeKey, dirty],
    );

    const confirmSwitch = () => {
        if (pendingKey) {
            forceRef.current = true;
            window.location.hash = pendingKey;
        }
        if (pendingUrl) {
            forceNavigateRef.current = true;
            router.visit(pendingUrl, { onFinish: () => (forceNavigateRef.current = false) });
        }
        setPendingKey(null);
        setPendingUrl(null);
    };

    const cancelSwitch = () => {
        setPendingKey(null);
        setPendingUrl(null);
    };

    const group = groups.find((candidate) => candidate.key === activeKey) ?? groups[0];
    const Icon = groupIcon(group?.icon ?? null);

    return (
        <AdminLayout
            breadcrumbs={[
                { title: 'Admin', href: '/admin/dashboard' },
                { title: t('settings.ui.title'), href: '/admin/settings' },
            ]}
        >
            <Head title={t('settings.ui.title')} />

            <div className="space-y-6 p-4 md:p-6">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight md:text-3xl">{t('settings.ui.title')}</h1>
                    <p className="text-muted-foreground">{t('settings.ui.description')}</p>
                </div>

                <div className="flex flex-col gap-6 lg:flex-row lg:items-start">
                    <aside className="lg:w-60 lg:shrink-0">
                        <GroupNav groups={groups} activeKey={group?.key ?? ''} dirtyKey={dirty ? activeKey : null} onSelect={handleSelect} />
                    </aside>

                    <div className="min-w-0 flex-1">
                        {group &&
                            (group.panel ? (
                                <div className="space-y-4">
                                    <Card>
                                        <CardHeader>
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
                                        </CardHeader>
                                    </Card>
                                    <SystemPanel system={system} />
                                </div>
                            ) : (
                                <GroupForm key={group.key} group={group} onDirtyChange={handleDirtyChange} />
                            ))}
                    </div>
                </div>
            </div>

            <ConfirmDialog
                open={pendingKey !== null || pendingUrl !== null}
                title={t('settings.ui.unsaved_changes')}
                description={t('settings.ui.switch_group_body')}
                confirmLabel={t('settings.ui.discard')}
                destructive
                onConfirm={confirmSwitch}
                onCancel={cancelSwitch}
            />
        </AdminLayout>
    );
}

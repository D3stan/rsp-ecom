import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';
import { type SystemItem, type SystemPanel as SystemPanelData } from '@/types';
import { Info } from 'lucide-react';
import { useMemo } from 'react';
import { UnitLabel } from './UnitLabel';

const STATUS_STYLES: Record<string, string> = {
    ok: 'border-transparent bg-emerald-600 text-white',
    problem: 'border-transparent bg-destructive text-white',
    unknown: 'border-transparent bg-secondary text-secondary-foreground',
};

function SystemTable({ rows }: { rows: Record<string, unknown>[] }) {
    const { t } = useTranslation();

    if (rows.length === 0) return <span className="text-muted-foreground">—</span>;

    const columns = Object.keys(rows[0]);

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[20rem] text-left text-xs">
                <thead>
                    <tr className="border-b text-muted-foreground">
                        {columns.map((column) => (
                            <th key={column} scope="col" className="py-1.5 pr-3 font-medium">
                                {t(`settings.ui.column.${column}`)}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {rows.map((row, index) => (
                        <tr key={index} className="border-b last:border-0">
                            {columns.map((column) => (
                                <td key={column} className="py-1.5 pr-3 font-mono">
                                    {String(row[column] ?? '')}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function SystemValue({ item }: { item: SystemItem }) {
    const { t, locale } = useTranslation();
    const value = item.value;

    switch (item.format) {
        case 'bool':
            return <Badge className={value ? STATUS_STYLES.ok : STATUS_STYLES.problem}>{value ? t('settings.ui.yes') : t('settings.ui.no')}</Badge>;
        case 'status': {
            const { status = 'unknown', checked_at: checkedAt = null } = (value ?? {}) as { status?: string; checked_at?: string | null };
            const checked = checkedAt ? new Date(checkedAt) : null;

            return (
                <span className="flex flex-wrap items-center gap-2">
                    <Badge className={STATUS_STYLES[status] ?? STATUS_STYLES.unknown}>
                        {t(`settings.ui.status.${STATUS_STYLES[status] ? status : 'unknown'}`)}
                    </Badge>
                    {checked && !Number.isNaN(checked.getTime()) && (
                        <span className="text-xs text-muted-foreground">
                            {t('settings.ui.last_checked', { date: checked.toLocaleString(locale) })}
                        </span>
                    )}
                </span>
            );
        }
        case 'table':
            return <SystemTable rows={Array.isArray(value) ? (value as Record<string, unknown>[]) : []} />;
        case 'number':
            return (
                <span className="inline-flex items-baseline gap-1.5">
                    <span className="font-mono text-sm">{String(value ?? '—')}</span>
                    <UnitLabel unit={item.unit} />
                </span>
            );
        default:
            return <span className="font-mono text-sm">{value === null || value === undefined || value === '' ? '—' : String(value)}</span>;
    }
}

/** Read-only "System" panel: where the shop is wired, never editable. */
export function SystemPanel({ system }: { system: SystemPanelData }) {
    const { t } = useTranslation();
    const sections = useMemo(() => system.sections.filter((section) => section.items.length > 0), [system]);

    return (
        <div className="space-y-4">
            <p className="flex items-center gap-2 text-sm text-muted-foreground">
                <Info className="size-4 shrink-0" aria-hidden="true" />
                {t('settings.ui.system_hint')}
            </p>
            {sections.map((section) => (
                <Card key={section.id}>
                    <CardHeader>
                        <CardTitle className="text-base">{section.label}</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <dl className="@container divide-y">
                            {section.items.map((item) => (
                                <div
                                    key={item.id}
                                    className="grid gap-1 py-3 first:pt-0 last:pb-0 @lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)] @lg:gap-6"
                                >
                                    <div className="space-y-1">
                                        <dt className="text-sm font-medium">{item.label}</dt>
                                        {item.help && <p className="text-xs text-muted-foreground">{item.help}</p>}
                                        <Badge variant="outline" className="font-normal">
                                            {item.sourceHint}
                                        </Badge>
                                    </div>
                                    <dd className="min-w-0">
                                        <SystemValue item={item} />
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

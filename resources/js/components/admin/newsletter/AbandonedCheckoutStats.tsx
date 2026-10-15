import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import useTranslation from '@/hooks/useTranslation';

export interface AbandonedCheckoutStatsData {
    enabled: boolean;
    days: number;
    sent: number;
    recovered: number;
    converted: number;
}

/** Abandoned-checkout reminders of the last N days: sent, carts recovered through the link, converted to a paid order. */
export default function AbandonedCheckoutStats({ stats }: { stats: AbandonedCheckoutStatsData }) {
    const { t } = useTranslation();

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">{t('newsletter.admin.abandoned.title')}</CardTitle>
                <CardDescription>
                    {stats.enabled ? t('newsletter.admin.abandoned.description', { days: stats.days }) : t('newsletter.admin.abandoned.off')}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <dl className="grid grid-cols-3 gap-4">
                    {(['sent', 'recovered', 'converted'] as const).map((key) => (
                        <div key={key}>
                            <dt className="text-sm text-muted-foreground">{t(`newsletter.admin.abandoned.${key}`)}</dt>
                            <dd className="text-2xl font-bold tabular-nums">{stats[key]}</dd>
                        </div>
                    ))}
                </dl>
            </CardContent>
        </Card>
    );
}

import useTranslation from '@/hooks/useTranslation';

/** "%", "EUR", "days"... Known words are translated (settings.ui.unit.*), anything else is shown as declared. */
export function UnitLabel({ unit }: { unit: string | null }) {
    const { t } = useTranslation();

    if (!unit) return null;

    const key = `settings.ui.unit.${unit}`;
    const translated = t(key);

    return <span className="text-sm text-muted-foreground">{translated === key ? unit : translated}</span>;
}

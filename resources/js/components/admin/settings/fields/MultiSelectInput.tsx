import { Badge } from '@/components/ui/badge';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import useTranslation from '@/hooks/useTranslation';
import { X } from 'lucide-react';
import { memo, useMemo, useState } from 'react';
import { type FieldInputProps } from './types';

interface MultiSelectInputProps extends FieldInputProps<string[]> {
    options: string[];
    optionLabel: (option: string) => string;
    /** shown next to the label in the list (the code for countries) */
    showCode?: boolean;
}

/** Pick several values out of a known list (countries): chips for the selection plus a searchable checklist. */
export const MultiSelectInput = memo(function MultiSelectInput({
    field,
    id,
    value,
    options,
    optionLabel,
    showCode = false,
    onChange,
    disabled,
    invalid,
    describedBy,
}: MultiSelectInputProps) {
    const { t, locale } = useTranslation();
    const [query, setQuery] = useState('');

    // already selected codes that are not in the list (stale data) stay selectable so they can be removed
    const all = useMemo(() => Array.from(new Set([...options, ...value])), [options, value]);
    const labels = useMemo(() => new Map(all.map((option) => [option, optionLabel(option)])), [all, optionLabel]);
    const sorted = useMemo(() => [...all].sort((a, b) => (labels.get(a) ?? a).localeCompare(labels.get(b) ?? b, locale)), [all, labels, locale]);
    const visible = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (needle === '') return sorted;

        return sorted.filter((option) => option.toLowerCase().includes(needle) || (labels.get(option) ?? '').toLowerCase().includes(needle));
    }, [sorted, labels, query]);
    const selected = useMemo(() => new Set(value), [value]);

    const toggle = (option: string, checked: boolean) => {
        const next = checked ? [...value.filter((item) => item !== option), option] : value.filter((item) => item !== option);
        onChange(field.name, next);
    };

    return (
        <div className="space-y-2" aria-describedby={describedBy}>
            <div className="flex min-h-7 flex-wrap gap-1.5" aria-live="polite">
                {value.map((option) => (
                    <Badge key={option} variant="secondary" className="gap-1 pr-1">
                        {labels.get(option) ?? option}
                        <button
                            type="button"
                            className="rounded-sm p-0.5 hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none"
                            onClick={() => toggle(option, false)}
                            disabled={disabled}
                            aria-label={t('settings.ui.remove_item', { item: labels.get(option) ?? option })}
                        >
                            <X className="size-3" aria-hidden="true" />
                        </button>
                    </Badge>
                ))}
            </div>
            <p className="text-xs text-muted-foreground">{t('settings.ui.selected_count', { count: value.length })}</p>
            <Input
                id={id}
                type="search"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
                placeholder={t('settings.ui.search')}
                aria-label={`${field.label} – ${t('settings.ui.search')}`}
                disabled={disabled}
                autoComplete="off"
                aria-invalid={invalid}
            />
            <div
                role="group"
                aria-label={field.label}
                className="max-h-56 overflow-y-auto rounded-md border"
                aria-disabled={disabled}
                data-invalid={invalid || undefined}
            >
                {visible.length === 0 ? (
                    <p className="px-3 py-2 text-sm text-muted-foreground">{t('settings.ui.no_results')}</p>
                ) : (
                    visible.map((option) => (
                        <label
                            key={option}
                            className="flex cursor-pointer items-center gap-2 px-3 py-1.5 text-sm hover:bg-accent has-[:disabled]:cursor-not-allowed"
                        >
                            <Checkbox
                                checked={selected.has(option)}
                                onCheckedChange={(checked) => toggle(option, checked === true)}
                                disabled={disabled}
                            />
                            <span className="min-w-0 flex-1 truncate">{labels.get(option) ?? option}</span>
                            {showCode && <span className="text-xs text-muted-foreground">{option}</span>}
                        </label>
                    ))
                )}
            </div>
        </div>
    );
});

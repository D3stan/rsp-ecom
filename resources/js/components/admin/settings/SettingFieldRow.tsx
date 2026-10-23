import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { type SettingField } from '@/types';
import { AlertTriangle, Lock, RotateCcw } from 'lucide-react';
import { memo, useCallback } from 'react';
import { countryName } from './countries';
import { MultiSelectInput } from './fields/MultiSelectInput';
import { NumberInput } from './fields/NumberInput';
import { RowsTableInput } from './fields/RowsTableInput';
import { SelectInput } from './fields/SelectInput';
import { SwitchInput } from './fields/SwitchInput';
import { TagListInput } from './fields/TagListInput';
import { TextInput } from './fields/TextInput';
import { type DraftValue, type FieldErrors, type RowDraft } from './values';

export interface SettingFieldRowProps {
    field: SettingField;
    value: DraftValue;
    /** allowed values taken from the current form value of the field this one depends on (memberOf) */
    options: string[];
    /** options are ISO country codes: show localized names */
    countryOptions: boolean;
    /** display names of the options (code => name), e.g. carrier names when the options are carrier codes */
    optionNames?: Record<string, string>;
    errors?: FieldErrors;
    /** label of the switch this field depends on, when that switch is off */
    disabledBy?: string;
    /** the whole form is being saved */
    busy: boolean;
    /** formatted default value, for the hint under customised fields */
    defaultText: string;
    onChange: (name: string, value: DraftValue) => void;
    onReset: (field: SettingField) => void;
}

/** One generated setting: label, badges, the control for its type, help text and errors. */
export const SettingFieldRow = memo(function SettingFieldRow({
    field,
    value,
    options,
    countryOptions,
    optionNames,
    errors,
    disabledBy,
    busy,
    defaultText,
    onChange,
    onReset,
}: SettingFieldRowProps) {
    const { t, locale } = useTranslation();
    const id = `setting-${field.key.replace(/[^a-z0-9]+/gi, '-')}`;
    const helpId = `${id}-help`;
    const errorId = `${id}-error`;
    const customised = field.source === 'customised';
    const disabled = field.readonly || disabledBy !== undefined || busy;

    const optionLabel = useCallback(
        (option: string) => (countryOptions ? `${countryName(option, locale)} (${option})` : option),
        [countryOptions, locale],
    );
    const listLabel = useCallback(
        (option: string) => (countryOptions ? countryName(option, locale) : (optionNames?.[option] ?? option)),
        [countryOptions, optionNames, locale],
    );

    const nested = errors?.nested ?? {};
    const hasNestedList = field.type !== 'json' && Object.keys(nested).length > 0;
    const invalid = errors?.own !== undefined || hasNestedList;
    const hasError = errors?.own !== undefined || hasNestedList;
    const describedBy = [field.help ? helpId : null, hasError ? errorId : null].filter(Boolean).join(' ') || undefined;
    const inline = field.type === 'bool';

    const common = { field, id, onChange, disabled, invalid, describedBy };

    let control;
    switch (field.type) {
        case 'bool':
            control = <SwitchInput {...common} value={Boolean(value)} />;
            break;
        case 'int':
        case 'float':
            control = <NumberInput {...common} value={String(value)} />;
            break;
        case 'enum':
            control = <SelectInput {...common} value={String(value)} options={options} optionLabel={optionLabel} />;
            break;
        case 'countries':
            control = <MultiSelectInput {...common} value={value as string[]} options={options} optionLabel={listLabel} showCode />;
            break;
        case 'list':
            control =
                options.length > 0 ? (
                    <MultiSelectInput {...common} value={value as string[]} options={options} optionLabel={listLabel} />
                ) : (
                    <TagListInput {...common} value={value as string[]} />
                );
            break;
        case 'json':
            control = <RowsTableInput {...common} value={value as RowDraft[]} nestedErrors={nested} />;
            break;
        default:
            control = <TextInput {...common} value={String(value)} />;
    }

    return (
        <div className={cn('space-y-2 py-5 first:pt-0 last:pb-0', field.readonly && 'opacity-90')}>
            <div className={cn('flex gap-4', inline ? 'items-start justify-between' : 'flex-col')}>
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <Label htmlFor={id} className="text-sm font-medium">
                            {field.label}
                            {field.required && (
                                <span aria-hidden="true" className="text-destructive">
                                    *
                                </span>
                            )}
                            {field.required && <span className="sr-only"> ({t('settings.ui.required')})</span>}
                        </Label>
                        <Badge variant={customised ? 'default' : 'secondary'} className="font-normal">
                            {customised ? t('settings.ui.customised') : t('settings.ui.default')}
                        </Badge>
                        {field.risk === 'high' && (
                            <Badge variant="outline" className="gap-1 border-amber-500/60 font-normal text-amber-700 dark:text-amber-400">
                                <AlertTriangle aria-hidden="true" />
                                {t('settings.ui.high_risk')}
                            </Badge>
                        )}
                        {field.readonly && (
                            <Badge variant="outline" className="gap-1 font-normal">
                                <Lock aria-hidden="true" />
                                {t(field.access === 'env' ? 'settings.ui.readonly_env' : 'settings.ui.readonly_fixed')}
                            </Badge>
                        )}
                    </div>
                    {field.help && (
                        <p id={helpId} className="text-sm text-muted-foreground">
                            {field.help}
                        </p>
                    )}
                    {disabledBy !== undefined && !field.readonly && (
                        <p className="text-xs text-muted-foreground">{t('settings.ui.depends_off', { setting: disabledBy })}</p>
                    )}
                </div>
                {inline && <div className="shrink-0 pt-0.5">{control}</div>}
            </div>

            {!inline && control}

            {hasError && (
                <div id={errorId} role="alert" className="space-y-0.5 text-sm text-destructive">
                    {errors?.own && <p>{errors.own}</p>}
                    {hasNestedList && Object.entries(nested).map(([path, message]) => <p key={path}>{message}</p>)}
                </div>
            )}

            {customised && !field.readonly && (
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <p className="text-xs text-muted-foreground">{t('settings.ui.default_value', { value: defaultText })}</p>
                    <Button type="button" variant="ghost" size="sm" className="h-7 px-2 text-xs" onClick={() => onReset(field)} disabled={busy}>
                        <RotateCcw aria-hidden="true" />
                        {t('settings.ui.reset_to_default')}
                    </Button>
                </div>
            )}
        </div>
    );
});

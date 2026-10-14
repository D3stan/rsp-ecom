import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import { Plus, Trash2 } from 'lucide-react';
import { memo } from 'react';
import { columnNames, emptyRow, type RowDraft } from '../values';
import { type FieldInputProps } from './types';

interface RowsTableInputProps extends FieldInputProps<RowDraft[]> {
    /** server errors of the cells: `1.code` (row index, column) */
    nestedErrors: Record<string, string>;
}

// A tracking link has to say where the tracking number goes. Only a hint: the server decides.
const hasNumberPlaceholder = (url: string) => url === '' || /^https?:\/\/\S*\{number\}\S*$/i.test(url);

/** Table (json) settings such as the carriers: one bordered group of inputs per row, add and remove rows. */
export const RowsTableInput = memo(function RowsTableInput({ field, id, value, onChange, disabled, describedBy, nestedErrors }: RowsTableInputProps) {
    const { t } = useTranslation();
    const columns = columnNames(field);
    const required = new Set((field.columns ?? []).filter((column) => column.required).map((column) => column.name));
    const limit = field.max ?? Infinity;

    const update = (index: number, column: string, text: string) =>
        onChange(
            field.name,
            value.map((row, i) => (i === index ? { ...row, [column]: text } : row)),
        );

    return (
        <div className="@container space-y-3" id={id} aria-describedby={describedBy}>
            {value.length === 0 && <p className="text-sm text-muted-foreground">{t('settings.ui.empty_list')}</p>}

            {value.map((row, index) => (
                <fieldset
                    key={row._key}
                    className="grid gap-3 rounded-md border p-3 @xl:grid-cols-[8rem_1fr_2fr_auto] @xl:items-start"
                    disabled={disabled}
                >
                    <legend className="sr-only">{t('settings.ui.row_number', { number: index + 1 })}</legend>
                    {columns.map((column) => {
                        const cellId = `${id}-${row._key}-${column}`;
                        const serverError = nestedErrors[`${index}.${column}`];
                        const hint =
                            column === 'tracking_url' && !hasNumberPlaceholder(row[column] ?? '') ? t('settings.ui.url_needs_number') : undefined;
                        const message = serverError ?? hint;
                        const messageId = message ? `${cellId}-message` : undefined;

                        return (
                            <div key={column} className="space-y-1">
                                <Label htmlFor={cellId} className="text-xs">
                                    {t(`settings.ui.column.${column}`)}
                                    {required.has(column) && (
                                        <span aria-hidden="true" className="text-destructive">
                                            *
                                        </span>
                                    )}
                                </Label>
                                <Input
                                    id={cellId}
                                    value={row[column] ?? ''}
                                    onChange={(event) => update(index, column, event.target.value)}
                                    autoComplete="off"
                                    aria-invalid={serverError !== undefined || undefined}
                                    aria-required={required.has(column)}
                                    aria-describedby={messageId}
                                    placeholder={column === 'tracking_url' ? 'https://…/{number}' : undefined}
                                />
                                {message && (
                                    <p
                                        id={messageId}
                                        className={serverError ? 'text-xs text-destructive' : 'text-xs text-amber-600 dark:text-amber-400'}
                                    >
                                        {message}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="justify-self-end text-muted-foreground hover:text-destructive sm:mt-5"
                        onClick={() =>
                            onChange(
                                field.name,
                                value.filter((_, i) => i !== index),
                            )
                        }
                        aria-label={t('settings.ui.remove_row_n', { number: index + 1 })}
                    >
                        <Trash2 aria-hidden="true" />
                    </Button>
                </fieldset>
            ))}

            <Button
                type="button"
                variant="outline"
                size="sm"
                disabled={disabled || value.length >= limit}
                onClick={() => onChange(field.name, [...value, emptyRow(field)])}
            >
                <Plus aria-hidden="true" />
                {t('settings.ui.add_row')}
            </Button>
        </div>
    );
});

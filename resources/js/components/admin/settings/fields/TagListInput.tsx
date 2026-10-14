import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import useTranslation from '@/hooks/useTranslation';
import { Plus, X } from 'lucide-react';
import { memo, useState, type KeyboardEvent } from 'react';
import { type FieldInputProps } from './types';

/** Free text list (keywords): chips plus an input; Enter or comma adds the entry. */
export const TagListInput = memo(function TagListInput({ field, id, value, onChange, disabled, invalid, describedBy }: FieldInputProps<string[]>) {
    const { t } = useTranslation();
    const [text, setText] = useState('');

    const add = () => {
        const entries = text
            .split(',')
            .map((entry) => entry.trim())
            .filter((entry) => entry !== '' && !value.includes(entry));

        if (entries.length > 0) {
            onChange(field.name, [...value, ...entries]);
        }
        setText('');
    };

    const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
        if (event.key === 'Enter' || event.key === ',') {
            event.preventDefault();
            add();
        }
    };

    return (
        <div className="space-y-2">
            {value.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('settings.ui.empty_list')}</p>
            ) : (
                <ul className="flex flex-wrap gap-1.5">
                    {value.map((entry) => (
                        <li key={entry}>
                            <Badge variant="secondary" className="gap-1 pr-1">
                                {entry}
                                <button
                                    type="button"
                                    className="rounded-sm p-0.5 hover:bg-foreground/10 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:pointer-events-none"
                                    onClick={() =>
                                        onChange(
                                            field.name,
                                            value.filter((item) => item !== entry),
                                        )
                                    }
                                    disabled={disabled}
                                    aria-label={t('settings.ui.remove_item', { item: entry })}
                                >
                                    <X className="size-3" aria-hidden="true" />
                                </button>
                            </Badge>
                        </li>
                    ))}
                </ul>
            )}
            <div className="flex gap-2">
                <Input
                    id={id}
                    value={text}
                    onChange={(event) => setText(event.target.value)}
                    onKeyDown={handleKeyDown}
                    placeholder={t('settings.ui.add_item_placeholder')}
                    disabled={disabled}
                    autoComplete="off"
                    aria-invalid={invalid}
                    aria-describedby={describedBy}
                />
                <Button type="button" variant="outline" onClick={add} disabled={disabled || text.trim() === ''}>
                    <Plus aria-hidden="true" />
                    {t('settings.ui.add_item')}
                </Button>
            </div>
        </div>
    );
});

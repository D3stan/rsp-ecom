import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { memo, type ChangeEvent } from 'react';
import { type FieldInputProps } from './types';

const INPUT_TYPES = { string: 'text', email: 'email', url: 'url' } as const;

/** string, email, url (single line) and text (multi line) settings. */
export const TextInput = memo(function TextInput({ field, id, value, onChange, disabled, invalid, describedBy }: FieldInputProps<string>) {
    const handleChange = (event: ChangeEvent<HTMLInputElement | HTMLTextAreaElement>) => onChange(field.name, event.target.value);
    const maxLength = field.max ?? undefined;

    if (field.type === 'text') {
        return (
            <Textarea
                id={id}
                value={value}
                onChange={handleChange}
                disabled={disabled}
                rows={4}
                maxLength={maxLength}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                aria-required={field.required}
            />
        );
    }

    return (
        <Input
            id={id}
            type={INPUT_TYPES[field.type as keyof typeof INPUT_TYPES] ?? 'text'}
            value={value}
            onChange={handleChange}
            disabled={disabled}
            maxLength={maxLength}
            placeholder={field.type === 'url' ? 'https://' : undefined}
            autoComplete="off"
            aria-invalid={invalid}
            aria-describedby={describedBy}
            aria-required={field.required}
        />
    );
});

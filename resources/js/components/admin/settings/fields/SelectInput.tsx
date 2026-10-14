import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { memo } from 'react';
import { type FieldInputProps } from './types';

interface SelectInputProps extends FieldInputProps<string> {
    options: string[];
    optionLabel: (option: string) => string;
}

/** enum settings (and fields restricted to another field's values, such as the default country). */
export const SelectInput = memo(function SelectInput({
    field,
    id,
    value,
    options,
    optionLabel,
    onChange,
    disabled,
    invalid,
    describedBy,
}: SelectInputProps) {
    // a stored value that is no longer allowed stays visible (the server rejects it with an explanation)
    const choices = value !== '' && !options.includes(value) ? [value, ...options] : options;

    return (
        <Select value={value} onValueChange={(next) => onChange(field.name, next)} disabled={disabled}>
            <SelectTrigger id={id} className="w-full max-w-sm" aria-invalid={invalid} aria-describedby={describedBy} aria-required={field.required}>
                <SelectValue />
            </SelectTrigger>
            <SelectContent>
                {choices.map((option) => (
                    <SelectItem key={option} value={option}>
                        {optionLabel(option)}
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
});

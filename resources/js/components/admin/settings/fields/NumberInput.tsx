import { Input } from '@/components/ui/input';
import { memo, type ChangeEvent } from 'react';
import { UnitLabel } from '../UnitLabel';
import { type FieldInputProps } from './types';

/** int and float settings, with the unit next to the input. */
export const NumberInput = memo(function NumberInput({ field, id, value, onChange, disabled, invalid, describedBy }: FieldInputProps<string>) {
    return (
        <div className="flex items-center gap-2">
            <Input
                id={id}
                type="number"
                inputMode={field.type === 'int' ? 'numeric' : 'decimal'}
                className="max-w-40"
                value={value}
                min={field.min ?? undefined}
                max={field.max ?? undefined}
                step={field.type === 'int' ? 1 : 'any'}
                onChange={(event: ChangeEvent<HTMLInputElement>) => onChange(field.name, event.target.value)}
                onWheel={(event) => event.currentTarget.blur()}
                disabled={disabled}
                aria-invalid={invalid}
                aria-describedby={describedBy}
                aria-required={field.required}
            />
            <UnitLabel unit={field.unit} />
        </div>
    );
});

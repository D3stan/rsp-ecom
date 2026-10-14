import { Switch } from '@/components/ui/switch';
import { memo } from 'react';
import { type FieldInputProps } from './types';

export const SwitchInput = memo(function SwitchInput({ field, id, value, onChange, disabled, describedBy }: FieldInputProps<boolean>) {
    return (
        <Switch
            id={id}
            checked={value}
            onCheckedChange={(checked) => onChange(field.name, checked)}
            disabled={disabled}
            aria-describedby={describedBy}
        />
    );
});

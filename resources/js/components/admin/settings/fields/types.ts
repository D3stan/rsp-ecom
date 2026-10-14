import { type SettingField } from '@/types';
import { type DraftValue } from '../values';

/** Props shared by every input component. `onChange` is one stable handler for the whole form (field name + new value). */
export interface FieldInputProps<V extends DraftValue = DraftValue> {
    field: SettingField;
    /** id of the control (the label points at it) */
    id: string;
    value: V;
    onChange: (name: string, value: V) => void;
    disabled: boolean;
    invalid: boolean;
    /** ids of the help / error texts */
    describedBy?: string;
}

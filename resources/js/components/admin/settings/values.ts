import { type SettingField, type SettingGroup } from '@/types';

/** One row of a table (json) setting while it is being edited: the cells plus a client-only React key. */
export type RowDraft = Record<string, string> & { _key: string };

/** Editable value of a field. Numbers are kept as the typed text until they are sent. */
export type DraftValue = string | boolean | string[] | RowDraft[];

export type GroupDraft = Record<string, DraftValue>;

let rowSequence = 0;

export const newRowKey = (): string => `row-${++rowSequence}`;

/** Column names of a table field (falls back to the keys of the first stored row). */
export function columnNames(field: SettingField): string[] {
    if (field.columns && field.columns.length > 0) {
        return field.columns.map((column) => column.name);
    }

    const first = Array.isArray(field.value) ? (field.value[0] as unknown as Record<string, unknown> | undefined) : undefined;

    return first ? Object.keys(first) : [];
}

export function emptyRow(field: SettingField): RowDraft {
    const row: Record<string, string> = { _key: newRowKey() };
    columnNames(field).forEach((name) => (row[name] = ''));

    return row as RowDraft;
}

export function toDraft(field: SettingField): DraftValue {
    const value: unknown = field.value;

    switch (field.type) {
        case 'bool':
            return Boolean(value);
        case 'list':
        case 'countries':
            return Array.isArray(value) ? value.map(String) : [];
        case 'json': {
            const columns = columnNames(field);

            return (Array.isArray(value) ? value : []).map((row: Record<string, unknown>) => {
                const draft: Record<string, string> = { _key: newRowKey() };
                columns.forEach((name) => (draft[name] = String(row?.[name] ?? '')));

                return draft as RowDraft;
            });
        }
        default:
            return value === null || value === undefined ? '' : String(value);
    }
}

export function draftFromGroup(group: SettingGroup): GroupDraft {
    const draft: GroupDraft = {};
    group.fields.forEach((field) => (draft[field.name] = toDraft(field)));

    return draft;
}

function parseNumber(text: string): number | string {
    const trimmed = text.trim();
    if (trimmed === '') return '';

    const number = Number(trimmed.replace(',', '.'));

    return Number.isNaN(number) ? trimmed : number;
}

/** The value sent to the server (the server coerces and validates again). */
export function toPayload(field: SettingField, draft: DraftValue): unknown {
    switch (field.type) {
        case 'int':
        case 'float':
            return parseNumber(String(draft));
        case 'json':
            return (draft as RowDraft[]).map((row) => {
                const cells: Record<string, string> = { ...row };
                delete cells._key;

                return cells;
            });
        default:
            return draft;
    }
}

/** Canonical text of a value, to compare a draft with the stored value without formatting noise. */
function canonical(field: SettingField, value: unknown): string {
    switch (field.type) {
        case 'int':
        case 'float': {
            const parsed = typeof value === 'number' ? value : parseNumber(String(value ?? ''));

            return JSON.stringify(parsed === '' ? null : parsed);
        }
        case 'email':
            return JSON.stringify(
                String(value ?? '')
                    .trim()
                    .toLowerCase(),
            );
        case 'countries':
            return JSON.stringify((Array.isArray(value) ? value : []).map((code) => String(code).trim().toUpperCase()));
        case 'list':
            return JSON.stringify((Array.isArray(value) ? value : []).map((item) => String(item).trim()));
        case 'json': {
            const columns = columnNames(field);

            return JSON.stringify(
                (Array.isArray(value) ? value : []).map((row: Record<string, unknown>) => columns.map((name) => String(row?.[name] ?? '').trim())),
            );
        }
        case 'bool':
            return JSON.stringify(Boolean(value));
        default:
            return JSON.stringify(String(value ?? '').trim());
    }
}

export function isFieldDirty(field: SettingField, draft: DraftValue | undefined): boolean {
    if (draft === undefined) return false;

    return canonical(field, toPayload(field, draft)) !== canonical(field, field.value);
}

/** Field errors by short name: `rate`, `destinations.0`, `carriers.1.code`. */
export interface FieldErrors {
    own?: string;
    nested: Record<string, string>;
}

export function groupErrors(errors: Record<string, string>): Record<string, FieldErrors> {
    const grouped: Record<string, FieldErrors> = {};

    Object.entries(errors).forEach(([key, message]) => {
        const [name, ...rest] = key.split('.');
        grouped[name] ??= { nested: {} };

        if (rest.length === 0) {
            grouped[name].own = message;
        } else {
            grouped[name].nested[rest.join('.')] = message;
        }
    });

    return grouped;
}

/** Readable text of a value for hints and the history table. */
export function formatValue(value: unknown, t: (key: string) => string): string {
    if (value === null || value === undefined || value === '') return '—';
    if (typeof value === 'boolean') return value ? t('settings.ui.yes') : t('settings.ui.no');
    if (Array.isArray(value)) {
        if (value.length === 0) return '—';

        return value
            .map((item) => {
                if (item !== null && typeof item === 'object') {
                    const cells = Object.entries(item as Record<string, unknown>)
                        .filter(([name, cell]) => name !== '_key' && cell !== '' && cell !== null)
                        .map(([, cell]) => String(cell));

                    return cells.slice(0, 2).join(' – ');
                }

                return String(item);
            })
            .join(', ');
    }
    if (typeof value === 'object') return JSON.stringify(value);

    return String(value);
}

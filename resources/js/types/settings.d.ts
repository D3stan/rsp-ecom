// Admin configuration system (docs/admin-settings.md). Keys are catalogue keys ("group.name").

export type SettingType = 'string' | 'text' | 'email' | 'url' | 'int' | 'float' | 'bool' | 'enum' | 'list' | 'countries' | 'json';

export interface CarrierRow {
    code: string;
    name: string;
    tracking_url: string;
}

/** Shared Inertia prop `settings`: only the settings marked `public` in config/settings.php. */
export interface PublicSettings {
    'store.name': string;
    'store.legal_name': string;
    'store.vat_number': string;
    'store.address': string;
    'store.currency': string;
    'contact.support_email': string;
    'contact.phone': string;
    'contact.business_hours': string;
    'social.instagram': string;
    'social.tiktok': string;
    'social.facebook': string;
    'tax.rate': number;
    'tax.prices_include_tax': boolean;
    'shipping.dispatch_days_min': number;
    'shipping.dispatch_days_max': number;
    'shipping.delivery_days_min': number;
    'shipping.delivery_days_max': number;
    'shipping.destinations': string[];
    'shipping.default_country': string;
    'shipping.free_threshold': number;
    'legal.withdrawal_days': number;
    'orders.min_amount': number;
    'orders.max_qty_per_line': number;
    'orders.guest_checkout_enabled': boolean;
    'orders.public_link_days': number;
    'orders.order_notes_max': number;
    'inventory.low_stock_threshold': number;
    'catalog.per_page': number;
}

/** Catalogue key of a setting shared with the frontend (`settings` Inertia prop). */
export type SettingKey = keyof PublicSettings;

export type SettingValue = string | number | boolean | string[] | CarrierRow[];

/** One field of the admin page (labels/help already translated by the server). */
export interface SettingField {
    key: string;
    /** short name: the request field name in PATCH admin/settings/{group} */
    name: string;
    type: SettingType;
    value: SettingValue;
    default: SettingValue;
    source: 'default' | 'customised';
    label: string;
    help: string;
    unit: string | null;
    min: number | null;
    max: number | null;
    /** enum values; for fields with memberOf: the current values of that other field */
    options: string[];
    readonly: boolean;
    access: 'admin' | 'readonly' | 'env';
    risk: 'normal' | 'high';
    required: boolean;
    dependsOn: string | null;
    atLeast: string | null;
    memberOf: string | null;
    public: boolean;
    rules: string[];
    /** json (table) fields only */
    columns: { name: string; required: boolean }[] | null;
}

export interface SettingGroup {
    key: string;
    labelKey: string;
    label: string;
    description: string;
    icon: string | null;
    /** true: no editable fields, shown from `system` instead */
    panel: boolean;
    fields: SettingField[];
}

export interface SystemItem {
    id: string;
    label: string;
    help: string;
    value: unknown;
    format: 'text' | 'number' | 'bool' | 'status' | 'table';
    source: 'env' | 'config' | 'code' | 'runtime';
    sourceHint: string;
    unit: string | null;
}

export interface SystemPanel {
    sections: { id: string; label: string; items: SystemItem[] }[];
}

export interface SettingHistoryEntry {
    id: number;
    key: string;
    label: string;
    old: unknown;
    new: unknown;
    user: { id: number; name: string } | null;
    created_at: string | null;
}

export interface SettingsPageProps {
    groups: SettingGroup[];
    system: SystemPanel;
    historyLimit: number;
}

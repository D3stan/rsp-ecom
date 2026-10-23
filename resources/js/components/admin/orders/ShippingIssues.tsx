import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import { Link, useForm } from '@inertiajs/react';
import { AlertCircle } from 'lucide-react';
import { FormEvent, useEffect, useState } from 'react';

export interface ShippingIssue {
    provider: string;
    kind: 'address' | 'settings' | 'parcel' | 'unavailable' | 'other';
    field: string | null;
    code: string;
    message: string;
}

export interface ShippingAddress {
    first_name: string;
    last_name: string;
    company?: string | null;
    address_line_1: string;
    address_line_2?: string | null;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    phone?: string | null;
}

interface Props {
    orderId: number;
    address: ShippingAddress | null;
    issues: ShippingIssue[];
    providerName?: (provider: string) => string;
    onEditParcels: () => void;
    onFixed: () => void;
}

// the field a carrier names (recipient.street) => the label key and the address form inputs it points at
const FIELDS: Record<string, { label: string; inputs: string[] }> = {
    postal_code: { label: 'admin.orders.postal_code', inputs: ['postal_code'] },
    city: { label: 'admin.orders.city', inputs: ['city'] },
    street: { label: 'admin.orders.address_line_1', inputs: ['address_line_1', 'address_line_2'] },
    province: { label: 'admin.orders.state', inputs: ['state'] },
    country: { label: 'admin.orders.country', inputs: ['country'] },
    phone: { label: 'admin.orders.ship.field_phone', inputs: ['phone'] },
    name: { label: 'admin.orders.first_name', inputs: ['first_name', 'last_name'] },
};

const INPUTS = [
    ['first_name', 'admin.orders.first_name'],
    ['last_name', 'admin.orders.last_name'],
    ['company', 'admin.orders.ship.field_company'],
    ['address_line_1', 'admin.orders.address_line_1'],
    ['address_line_2', 'admin.orders.address_line_2'],
    ['city', 'admin.orders.city'],
    ['state', 'admin.orders.state'],
    ['postal_code', 'admin.orders.postal_code'],
    ['country', 'admin.orders.country'],
    ['phone', 'admin.orders.ship.field_phone'],
] as const;

const recipientField = (issue: ShippingIssue) => (issue.field ?? '').replace('recipient.', '');

const seed = (address: ShippingAddress | null): Record<string, string> =>
    Object.fromEntries(INPUTS.map(([name]) => [name, String(address?.[name] ?? '')]));

export default function ShippingIssues({ orderId, address, issues, providerName = (p) => p, onEditParcels, onFixed }: Props) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [saved, setSaved] = useState(false);
    const form = useForm<Record<string, string>>(seed(address));

    useEffect(() => setSaved(false), [issues]);

    if (issues.length === 0) {
        return null;
    }

    // the store check and the carrier often name the same field: show it once
    const seen = new Set<string>();
    const list = issues.filter((issue) => {
        const key = issue.field ? `${issue.kind}:${issue.field}` : `${issue.provider}:${issue.message}`;
        return !seen.has(key) && !!seen.add(key);
    });

    const addressIssues = list.filter((issue) => issue.kind === 'address');
    const marked = new Map<string, ShippingIssue>();
    addressIssues.forEach((issue) => FIELDS[recipientField(issue)]?.inputs.forEach((input) => marked.has(input) || marked.set(input, issue)));

    const label = (issue: ShippingIssue) => {
        const field = recipientField(issue);
        return FIELDS[field] ? t(FIELDS[field].label) : field || issue.provider;
    };

    const text = (issue: ShippingIssue) => {
        const params = { provider: providerName(issue.provider), field: label(issue), message: issue.message };
        switch (issue.kind) {
            case 'address':
                return t(
                    issue.code === 'required'
                        ? 'admin.orders.ship.issue_required'
                        : issue.code === 'invalid'
                          ? 'admin.orders.ship.issue_invalid'
                          : 'admin.orders.ship.issue_address',
                    params,
                );
            case 'settings':
                return t('admin.orders.ship.issue_settings', params);
            case 'parcel':
                return t('admin.orders.ship.issue_parcel', params);
            case 'unavailable':
                return t('admin.orders.ship.issue_unavailable', params);
            default:
                return t('admin.orders.ship.issue_other', params);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.patch(`/admin/orders/${orderId}/shipping-address`, {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                setSaved(true);
                onFixed();
            },
        });
    };

    return (
        <div role="alert" className="space-y-2 rounded-md border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">
            <div className="flex items-center gap-2 font-medium">
                <AlertCircle className="h-4 w-4 shrink-0" />
                {t('admin.orders.ship.issues_title')}
            </div>
            <ul className="space-y-1 pl-6 text-xs">
                {list.map((issue, i) => (
                    <li key={i} className="list-disc">
                        {text(issue)}{' '}
                        {(issue.kind === 'settings' || issue.kind === 'unavailable') && (
                            <Link href="/admin/settings/shipping" className="font-medium underline">
                                {t('admin.orders.ship.settings_link')}
                            </Link>
                        )}
                        {issue.kind === 'parcel' && (
                            <button type="button" className="font-medium underline" onClick={onEditParcels}>
                                {t('admin.orders.ship.edit_parcels')}
                            </button>
                        )}
                    </li>
                ))}
            </ul>

            {addressIssues.length > 0 && address && (
                <div className="pl-6">
                    {saved ? (
                        <p role="status" className="text-xs">
                            {t('admin.orders.ship.address_saved')}
                        </p>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => {
                                form.setData(seed(address));
                                form.clearErrors();
                                setOpen(true);
                            }}
                        >
                            {t('admin.orders.ship.fix_address')}
                        </Button>
                    )}
                </div>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('admin.orders.ship.fix_address')}</DialogTitle>
                    </DialogHeader>
                    <form onSubmit={submit} className="grid gap-3 text-foreground sm:grid-cols-2">
                        {INPUTS.map(([name, labelKey]) => {
                            const issue = marked.get(name);
                            const error = form.errors[name] ?? (issue ? text(issue) : undefined);
                            return (
                                <div
                                    key={name}
                                    className={name === 'address_line_1' || name === 'address_line_2' ? 'space-y-1 sm:col-span-2' : 'space-y-1'}
                                >
                                    <Label htmlFor={`fix-${name}`}>{t(labelKey)}</Label>
                                    <Input
                                        id={`fix-${name}`}
                                        value={form.data[name]}
                                        maxLength={name === 'country' ? 2 : undefined}
                                        aria-invalid={!!error}
                                        aria-describedby={error ? `fix-${name}-error` : undefined}
                                        onChange={(e) => form.setData(name, name === 'country' ? e.target.value.toUpperCase() : e.target.value)}
                                    />
                                    {error && (
                                        <p id={`fix-${name}-error`} className="text-xs text-destructive">
                                            {error}
                                        </p>
                                    )}
                                </div>
                            );
                        })}
                        <DialogFooter className="sm:col-span-2">
                            <Button type="submit" disabled={form.processing}>
                                {t('admin.orders.ship.save_address')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    );
}

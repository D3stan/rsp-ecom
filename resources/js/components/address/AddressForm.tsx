import StateField from '@/components/address/StateField';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { lookupItalianPostcode, provinceCode } from '@/lib/italian-address';
import { useForm } from '@inertiajs/react';
import { type FormEventHandler, useRef } from 'react';

export interface AddressEntry {
    id: number;
    is_default: boolean;
    first_name: string;
    last_name: string;
    company: string | null;
    address_line_1: string;
    address_line_2: string | null;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    phone: string | null;
}

interface AddressFormProps {
    /** The entry being edited; absent for a new address */
    address?: AddressEntry;
    provinces: Record<string, string>;
    onDone: () => void;
}

/** Create / edit form of an address-book entry (settings > addresses). */
export default function AddressForm({ address, provinces, onDone }: AddressFormProps) {
    const { t, locale } = useTranslation();
    const destinations = useSetting('shipping.destinations', ['IT']);
    const defaultCountry = useSetting('shipping.default_country', 'IT');
    const autofilled = useRef({ city: false, state: false });

    const country = address?.country && destinations.includes(address.country) ? address.country : defaultCountry;
    const { data, setData, post, put, processing, errors, clearErrors } = useForm({
        first_name: address?.first_name ?? '',
        last_name: address?.last_name ?? '',
        company: address?.company ?? '',
        address_line_1: address?.address_line_1 ?? '',
        address_line_2: address?.address_line_2 ?? '',
        city: address?.city ?? '',
        state: country === 'IT' ? provinceCode(provinces, address?.state ?? '') : (address?.state ?? ''),
        postal_code: address?.postal_code ?? '',
        country,
        phone: address?.phone ?? '',
        is_default: address?.is_default ?? false,
    });

    const countryName = (code: string): string => {
        try {
            return new Intl.DisplayNames([locale], { type: 'region' }).of(code) ?? code;
        } catch {
            return code;
        }
    };

    const set = (field: keyof typeof data, value: string | boolean) => {
        clearErrors(field);
        if (field === 'city' || field === 'state') autofilled.current[field] = false;
        setData(field, value as never);
        if (field === 'country' && value === 'IT') setData('state', provinceCode(provinces, data.state));
    };

    const setPostalCode = (value: string) => {
        set('postal_code', value);

        if (data.country !== 'IT') return;

        void lookupItalianPostcode(value.trim()).then((hit) => {
            if (!hit) return;
            if (hit.city && (!data.city || autofilled.current.city)) {
                setData('city', hit.city);
                autofilled.current.city = true;
            }
            if (!data.state || autofilled.current.state) {
                setData('state', hit.state);
                autofilled.current.state = true;
            }
        });
    };

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        const options = { preserveScroll: true, onSuccess: onDone };

        if (address) {
            put(route('addresses.update', address.id), options);
        } else {
            post(route('addresses.store'), options);
        }
    };

    const field = (
        key: keyof typeof data,
        label: string,
        props: { required?: boolean; autoComplete?: string; type?: string; onChange?: (value: string) => void } = {},
    ) => (
        <div className="grid gap-1">
            <Label htmlFor={`address-${key}`}>
                {label}
                {props.required ? ' *' : ''}
            </Label>
            <Input
                id={`address-${key}`}
                type={props.type}
                autoComplete={props.autoComplete}
                value={String(data[key])}
                onChange={(e) => (props.onChange ?? ((value) => set(key, value)))(e.target.value)}
                aria-invalid={errors[key] ? true : undefined}
                aria-describedby={errors[key] ? `address-${key}-error` : undefined}
            />
            <InputError id={`address-${key}-error`} message={errors[key]} />
        </div>
    );

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-lg border p-4" noValidate>
            <div className="grid gap-4 sm:grid-cols-2">
                {field('first_name', t('checkout.first_name'), { required: true, autoComplete: 'given-name' })}
                {field('last_name', t('checkout.last_name'), { required: true, autoComplete: 'family-name' })}
            </div>
            {field('company', `${t('checkout.company')} (${t('checkout.optional')})`, { autoComplete: 'organization' })}
            {field('address_line_1', t('checkout.address_line_1'), { required: true, autoComplete: 'address-line1' })}
            {field('address_line_2', `${t('checkout.address_line_2')} (${t('checkout.optional')})`, { autoComplete: 'address-line2' })}
            <div className="grid gap-4 sm:grid-cols-3">
                {field('postal_code', t('checkout.postal_code'), { required: true, autoComplete: 'postal-code', onChange: setPostalCode })}
                {field('city', t('checkout.city'), { required: true, autoComplete: 'address-level2' })}
                <div className="grid gap-1">
                    <Label htmlFor="address-state">{t('checkout.state')} *</Label>
                    <StateField
                        id="address-state"
                        country={data.country}
                        provinces={provinces}
                        value={data.state}
                        onChange={(value) => set('state', value)}
                        invalidProps={errors.state ? { 'aria-invalid': true, 'aria-describedby': 'address-state-error' } : {}}
                    />
                    <InputError id="address-state-error" message={errors.state} />
                </div>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1">
                    <Label htmlFor="address-country">{t('checkout.country')} *</Label>
                    <Select value={data.country} onValueChange={(value) => set('country', value)}>
                        <SelectTrigger id="address-country">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {destinations.map((code) => (
                                <SelectItem key={code} value={code}>
                                    {countryName(code)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <InputError message={errors.country} />
                </div>
                {field('phone', t('checkout.phone'), { autoComplete: 'tel', type: 'tel' })}
            </div>

            {!address?.is_default && (
                <div className="flex items-center gap-2">
                    <Checkbox id="address-is_default" checked={data.is_default} onCheckedChange={(checked) => set('is_default', checked === true)} />
                    <Label htmlFor="address-is_default">{t('account.addresses.make_default')}</Label>
                </div>
            )}

            <div className="flex gap-2">
                <Button type="submit" disabled={processing}>
                    {t('account.addresses.save')}
                </Button>
                <Button type="button" variant="ghost" onClick={onDone}>
                    {t('account.addresses.cancel')}
                </Button>
            </div>
        </form>
    );
}

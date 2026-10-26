import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import useTranslation from '@/hooks/useTranslation';
import { provinceCode } from '@/lib/italian-address';

interface StateFieldProps {
    id: string;
    country: string;
    provinces: Record<string, string>;
    value: string;
    onChange: (value: string) => void;
    className?: string;
    invalidProps?: Record<string, unknown>;
}

/** Province dropdown for Italy (the code is what is stored and sent to the carriers); free text elsewhere. */
export default function StateField({ id, country, provinces, value, onChange, className, invalidProps = {} }: StateFieldProps) {
    const { t } = useTranslation();

    if (country !== 'IT') {
        return (
            <Input
                id={id}
                name="state"
                autoComplete="address-level1"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className={className}
                {...invalidProps}
            />
        );
    }

    return (
        <Select name="state" autoComplete="address-level1" value={provinceCode(provinces, value)} onValueChange={onChange}>
            <SelectTrigger id={id} className={className} {...invalidProps}>
                <SelectValue placeholder={t('checkout.province_select')} />
            </SelectTrigger>
            <SelectContent className="max-h-72 bg-white text-black">
                {Object.entries(provinces).map(([code, name]) => (
                    <SelectItem key={code} value={code}>
                        {name} ({code})
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

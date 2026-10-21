import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { useTranslation } from '@/hooks/useTranslation';

interface Props {
    value: string;
    onChange: (value: string) => void;
    error?: string;
}

/** GPSR safety information of a product (products.safety_information), shown on the product page. */
export function SafetyInformationField({ value, onChange, error }: Props) {
    const { t } = useTranslation();

    return (
        <div className="md:col-span-2">
            <Label htmlFor="safety_information">{t('admin.products.safety.label')}</Label>
            <Textarea
                id="safety_information"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                rows={4}
                maxLength={5000}
                placeholder={t('admin.products.safety.placeholder')}
                className={error ? 'border-red-500' : ''}
            />
            {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
            <p className="mt-1 text-xs text-gray-500">{t('admin.products.safety.help_text')}</p>
        </div>
    );
}

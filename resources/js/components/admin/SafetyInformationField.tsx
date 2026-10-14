import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

interface Props {
    value: string;
    onChange: (value: string) => void;
    error?: string;
}

/** GPSR safety information of a product (products.safety_information), shown on the product page. */
export function SafetyInformationField({ value, onChange, error }: Props) {
    return (
        <div className="md:col-span-2">
            <Label htmlFor="safety_information">Safety information</Label>
            <Textarea
                id="safety_information"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                rows={4}
                maxLength={5000}
                placeholder="Warnings, installation and usage precautions"
                className={error ? 'border-red-500' : ''}
            />
            {error && <p className="mt-1 text-sm text-red-500">{error}</p>}
            <p className="mt-1 text-xs text-gray-500">
                Shown to customers on the product page under "Product safety", together with the manufacturer details (Settings → Product safety).
                Written in one language: products have no translations.
            </p>
        </div>
    );
}

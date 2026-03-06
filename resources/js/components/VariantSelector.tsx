import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface Variant {
    id: number;
    name: string;
    price: number;
    stock_quantity: number;
    is_default: boolean;
}

interface VariantSelectorProps {
    variants: Variant[];
    selectedVariantId: number;
    onVariantChange: (variantId: number) => void;
    label?: string;
}

export function VariantSelector({
    variants,
    selectedVariantId,
    onVariantChange,
    label = 'Variante',
}: VariantSelectorProps) {
    // Don't render if only one variant
    if (variants.length <= 1) {
        return null;
    }

    return (
        <div>
            <Label className="mb-3 block text-sm font-medium text-gray-900">{label}</Label>
            <Select value={selectedVariantId.toString()} onValueChange={(value) => onVariantChange(Number(value))}>
                <SelectTrigger className="w-full text-black">
                    <SelectValue placeholder="Seleziona variante" />
                </SelectTrigger>
                <SelectContent className="bg-white text-gray-900">
                    {variants.map((variant) => (
                        <SelectItem key={variant.id} value={variant.id.toString()}>
                            <span className="flex items-center justify-between gap-4">
                                <span>{variant.name}</span>
                                <span className="text-sm text-gray-500">
                                    {variant.stock_quantity > 0 ? `${variant.stock_quantity} disponibili` : 'Esaurito'}
                                </span>
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

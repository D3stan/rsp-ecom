import { useMoney } from '@/hooks/use-money';
import { useTranslation } from '@/hooks/useTranslation';

interface ShippingLineProps {
    shippingCost: number;
    freeShippingRemaining?: number | null;
    className?: string;
    amountClassName?: string;
}

export default function ShippingLine({ shippingCost, freeShippingRemaining, className, amountClassName }: ShippingLineProps) {
    const { t } = useTranslation();
    const formatCurrency = useMoney();
    const isFree = freeShippingRemaining === 0;

    return (
        <div className="space-y-1">
            <div className={className ?? 'flex justify-between'}>
                <span>{t('cart.shipping')}</span>
                <span className={isFree ? 'font-medium text-green-600' : amountClassName}>
                    {isFree ? t('shipping.free') : formatCurrency(shippingCost)}
                </span>
            </div>
            {freeShippingRemaining != null && freeShippingRemaining > 0 && (
                <p className="text-sm text-gray-500">{t('shipping.free_remaining', { amount: formatCurrency(freeShippingRemaining) })}</p>
            )}
        </div>
    );
}

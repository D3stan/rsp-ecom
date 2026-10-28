import ShippingLine from '@/components/checkout/ShippingLine';
import { formatRate, useMoney } from '@/hooks/use-money';
import { useTranslation } from '@/hooks/useTranslation';
import { fallbackToDefaultImage } from '@/lib/utils';

/** One line of the checkout details props (CheckoutController::summaryLines). */
export interface SummaryLine {
    id: number;
    name: string;
    variant_name: string | null;
    image: string;
    quantity: number;
    price: number;
    total: number;
}

interface OrderSummaryProps {
    lines: SummaryLine[];
    subtotal: number;
    discountAmount: number;
    discountDescription?: string;
    shippingCost: number;
    freeShippingRemaining?: number | null;
    total: number;
    taxAmount: number;
    /** VAT rate (percent) the VAT portion is computed at; null when no VAT breakdown applies */
    taxRate: number | null;
}

/** What is being bought and what it costs: the lines, then subtotal, discount, shipping, total and the VAT included. */
export default function OrderSummary({
    lines,
    subtotal,
    discountAmount,
    discountDescription,
    shippingCost,
    freeShippingRemaining,
    total,
    taxAmount,
    taxRate,
}: OrderSummaryProps) {
    const { t, locale } = useTranslation();
    const formatCurrency = useMoney();

    return (
        <div className="space-y-4">
            <ul className="divide-y divide-gray-200">
                {lines.map((line) => (
                    <li key={line.id} className="flex items-center gap-3 py-3 first:pt-0">
                        <img
                            src={line.image}
                            alt=""
                            onError={fallbackToDefaultImage}
                            width={56}
                            height={56}
                            className="h-14 w-14 flex-shrink-0 rounded-md border border-gray-200 object-cover"
                        />
                        <div className="min-w-0 flex-1">
                            <p className="truncate font-medium text-black">{line.name}</p>
                            {line.variant_name && <p className="truncate text-sm text-gray-600">{line.variant_name}</p>}
                            <p className="text-sm text-gray-600">
                                {t('cart.quantity')}: {line.quantity}
                                {line.quantity > 1 && ` × ${formatCurrency(line.price)}`}
                            </p>
                        </div>
                        <span className="font-medium whitespace-nowrap text-black">{formatCurrency(line.total)}</span>
                    </li>
                ))}
            </ul>

            <div className="space-y-3 border-t border-gray-200 pt-4">
                <div className="flex justify-between text-gray-600">
                    <span>{t('cart.subtotal')}</span>
                    <span className="font-medium text-black">{formatCurrency(subtotal)}</span>
                </div>
                {discountAmount > 0 && (
                    <div className="flex justify-between gap-2 text-green-600">
                        <span>
                            {t('cart.discount')} {discountDescription && `(${discountDescription})`}
                        </span>
                        <span className="font-medium">-{formatCurrency(discountAmount)}</span>
                    </div>
                )}
                <ShippingLine
                    shippingCost={shippingCost}
                    freeShippingRemaining={freeShippingRemaining}
                    className="flex justify-between text-gray-600"
                    amountClassName="font-medium text-black"
                />
                <div className="border-t border-gray-200 pt-3">
                    <div className="flex justify-between text-lg font-semibold text-black">
                        <span>{t('cart.total')}</span>
                        <span>{formatCurrency(total)}</span>
                    </div>
                    {taxRate !== null && (
                        <div className="mt-1 flex justify-between text-sm text-gray-500">
                            <span>{t('checkout.vat_portion', { rate: formatRate(taxRate, locale) })}</span>
                            <span>{formatCurrency(taxAmount)}</span>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}

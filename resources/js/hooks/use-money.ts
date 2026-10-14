import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { formatCurrency } from '@/lib/utils';

/** Formats an amount in the shop currency (setting store.currency) and the visitor's language. */
export function useMoney(): (amount: number) => string {
    const { locale } = useTranslation();
    const currency = useSetting('store.currency', 'EUR');

    return (amount: number) => formatCurrency(amount, currency, locale);
}

/** "22", "5,5": a VAT rate in the visitor's number format, for the {rate} placeholder of checkout.vat_portion. */
export function formatRate(rate: number, locale: string): string {
    return new Intl.NumberFormat(locale, { maximumFractionDigits: 2 }).format(rate);
}

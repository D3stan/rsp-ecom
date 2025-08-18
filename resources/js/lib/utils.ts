import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function formatCurrency(amount: number, currency: string = 'EUR', locale: string = 'it-IT'): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

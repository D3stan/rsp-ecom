import { type ClassValue, clsx } from 'clsx';
import type { SyntheticEvent } from 'react';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export const DEFAULT_PRODUCT_IMAGE = '/images/product.png';

export function fallbackToDefaultImage(event: SyntheticEvent<HTMLImageElement>): void {
    if (!event.currentTarget.src.endsWith(DEFAULT_PRODUCT_IMAGE)) {
        event.currentTarget.src = DEFAULT_PRODUCT_IMAGE;
    }
}

export function formatCurrency(amount: number, currency: string = 'EUR', locale: string = 'it-IT'): string {
    return new Intl.NumberFormat(locale, {
        style: 'currency',
        currency: currency,
    }).format(amount);
}

import OrderStatusTimeline from '@/components/orders/OrderStatusTimeline';
import { TranslationProvider } from '@/contexts/TranslationContext';
import { renderToString } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

const translations = {
    'orders.timeline.order_placed': 'Order Placed',
    'orders.timeline.processing': 'Processing',
    'orders.timeline.shipped': 'Shipped',
    'orders.timeline.delivered': 'Delivered',
    'orders.cancelled_state': 'This order was cancelled.',
};

function renderWithTranslations(status: string) {
    return renderToString(
        <TranslationProvider initialTranslations={translations}>
            <OrderStatusTimeline status={status} />
        </TranslationProvider>,
    );
}

describe('OrderStatusTimeline', () => {
    it('renders 4 steps for active orders', () => {
        const html = renderWithTranslations('processing');
        expect(html).toContain('Order Placed');
        expect(html).toContain('Processing');
        expect(html).toContain('Shipped');
        expect(html).toContain('Delivered');
    });

    it('renders dedicated cancellation indicator for cancelled orders', () => {
        const html = renderWithTranslations('cancelled');
        expect(html).toContain('This order was cancelled.');
        expect(html).not.toContain('Order Placed');
    });

    it('handles uppercase CANCELLED status correctly', () => {
        const html = renderWithTranslations('CANCELLED');
        expect(html).toContain('This order was cancelled.');
        expect(html).not.toContain('Order Placed');
    });
});

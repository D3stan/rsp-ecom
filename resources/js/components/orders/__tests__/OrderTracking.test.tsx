import OrderTracking from '@/components/orders/OrderTracking';
import { TranslationProvider } from '@/contexts/TranslationContext';
import { renderToString } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

const translations = {
    'orders.cancelled_state': 'This order was cancelled.',
    'orders.refunded_state': 'This order was refunded.',
    'orders.track_package': 'Track package',
    'orders.track_with': 'Track with {carrier}',
    'orders.no_updates_yet': 'No tracking updates yet.',
    'orders.copy_tracking_number': 'Copy tracking number',
    'orders.copied': 'Copied!',
    'tracking_admin.status.in_transit': 'In Transit',
};

function renderWithTranslations(order: Parameters<typeof OrderTracking>[0]['order']) {
    return renderToString(
        <TranslationProvider initialTranslations={translations}>
            <OrderTracking order={order} />
        </TranslationProvider>,
    );
}

describe('OrderTracking', () => {
    it('returns null for processing or pending orders even if tracking_number exists', () => {
        const htmlProcessing = renderWithTranslations({
            status: 'processing',
            tracking_number: 'TRK123456',
        });
        expect(htmlProcessing).toBe('');

        const htmlPending = renderWithTranslations({
            status: 'pending',
            tracking_number: 'TRK123456',
        });
        expect(htmlPending).toBe('');
    });

    it('renders tracking number and tracking status simultaneously for shipped orders', () => {
        const html = renderWithTranslations({
            status: 'shipped',
            tracking_number: 'TRK123456',
            tracking_status: 'in_transit',
        });
        expect(html).toContain('TRK123456');
        expect(html).toContain('In Transit');
    });

    it('renders cancelled state for cancelled orders', () => {
        const html = renderWithTranslations({
            status: 'cancelled',
        });
        expect(html).toContain('This order was cancelled.');
    });

    it('renders refunded state for cancelled orders with refunded payment', () => {
        const html = renderWithTranslations({
            status: 'cancelled',
            payment_status: 'refunded',
        });
        expect(html).toContain('This order was refunded.');
    });

    it('safely handles invalid tracking dates without crashing', () => {
        const html = renderWithTranslations({
            status: 'shipped',
            tracking_number: 'TRK123456',
            tracking_last_synced_at: 'invalid-date-string',
        });
        expect(html).toContain('TRK123456');
        expect(html).not.toContain('Invalid Date');
    });
});

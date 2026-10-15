import { forwardTransitions } from '@/lib/order-transitions';
import { describe, expect, it } from 'vitest';

// G2: "shipped" is reached only through the ship form (carrier, tracking, one email), never through a status button
describe('forwardTransitions', () => {
    it('never offers "shipped" as a status button, even when the server allows the move', () => {
        expect(forwardTransitions('processing', ['shipped', 'cancelled'])).toEqual([]);
    });

    it('keeps the other forward moves', () => {
        expect(forwardTransitions('pending', ['processing', 'cancelled'])).toEqual(['processing']);
        expect(forwardTransitions('shipped', ['delivered', 'processing'])).toEqual(['delivered']);
    });
});

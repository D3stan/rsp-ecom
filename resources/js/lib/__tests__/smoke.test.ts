import { cn } from '@/lib/utils';
import { describe, expect, it } from 'vitest';

describe('vitest setup', () => {
    it('resolves the @/ alias and runs pure TS', () => {
        expect(cn('a', undefined, 'c')).toBe('a c');
    });
});

import { afterEach, describe, expect, it, vi } from 'vitest';
import { lookupItalianPostcode, provinceCode } from '../italian-address';

const provinces = { MI: 'Milano', RE: 'Reggio Emilia', BZ: 'Bolzano/Bozen' };

describe('provinceCode', () => {
    it('keeps a code, whatever its case', () => {
        expect(provinceCode(provinces, 'mi')).toBe('MI');
    });

    it('maps a province name saved as free text to its code', () => {
        expect(provinceCode(provinces, ' reggio emilia ')).toBe('RE');
    });

    it('is empty for something that is no province', () => {
        expect(provinceCode(provinces, 'Lombardia')).toBe('');
        expect(provinceCode(provinces, '')).toBe('');
    });
});

describe('lookupItalianPostcode', () => {
    afterEach(() => vi.unstubAllGlobals());

    it('only looks up 5 digits, without fetching anything else', async () => {
        const fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);

        expect(await lookupItalianPostcode('2012')).toBeNull();
        expect(await lookupItalianPostcode('20A21')).toBeNull();
        expect(fetchMock).not.toHaveBeenCalled();
    });

    it('returns the comune and province of a known CAP, and null for an unknown or unreachable table', async () => {
        vi.stubGlobal('fetch', vi.fn().mockResolvedValue({ json: async () => ({ '35031': ['Abano Terme', 'PD'], '00010': ['', 'RM'] }) }));

        expect(await lookupItalianPostcode('35031')).toEqual({ city: 'Abano Terme', state: 'PD' });
        expect(await lookupItalianPostcode('00010')).toEqual({ city: '', state: 'RM' });
        expect(await lookupItalianPostcode('99999')).toBeNull();
    });
});

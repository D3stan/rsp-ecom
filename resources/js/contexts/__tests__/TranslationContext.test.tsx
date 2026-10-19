import { TranslationProvider, useTranslation } from '@/contexts/TranslationContext';
import { renderToString } from 'react-dom/server';
import { describe, expect, it } from 'vitest';

function Consumer({ queryKey, replacements }: { queryKey: string; replacements?: Record<string, any> }) {
    const { t } = useTranslation();
    return <span>{t(queryKey, replacements)}</span>;
}

describe('TranslationContext defaultValue support', () => {
    it('returns defaultValue when key is missing', () => {
        const html = renderToString(
            <TranslationProvider initialTranslations={{ 'existing.key': 'Hello' }}>
                <Consumer queryKey="missing.key" replacements={{ defaultValue: 'Fallback text' }} />
            </TranslationProvider>,
        );
        expect(html).toContain('Fallback text');
    });

    it('interpolates placeholders in defaultValue when key is missing', () => {
        const html = renderToString(
            <TranslationProvider initialTranslations={{}}>
                <Consumer queryKey="missing.key" replacements={{ defaultValue: 'Save €{amount}', amount: '12.50' }} />
            </TranslationProvider>,
        );
        expect(html).toContain('Save €12.50');
    });

    it('returns existing translation rather than defaultValue when key is found', () => {
        const html = renderToString(
            <TranslationProvider initialTranslations={{ 'existing.key': 'Real Translation' }}>
                <Consumer queryKey="existing.key" replacements={{ defaultValue: 'Fallback text' }} />
            </TranslationProvider>,
        );
        expect(html).toContain('Real Translation');
    });

    it('returns raw key when translation is missing and no defaultValue is provided', () => {
        const html = renderToString(
            <TranslationProvider initialTranslations={{}}>
                <Consumer queryKey="missing.key" />
            </TranslationProvider>,
        );
        expect(html).toContain('missing.key');
    });
});

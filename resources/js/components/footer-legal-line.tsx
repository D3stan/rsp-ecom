import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';

/**
 * Company identification under the copyright line: legal name, VAT number and address from the store.* settings
 * (admin: Settings > Store). Each part is left out when empty; renders nothing when all three are empty.
 */
export default function FooterLegalLine() {
    const { t } = useTranslation();
    const legalName = useSetting('store.legal_name', '').trim();
    const vatNumber = useSetting('store.vat_number', '').trim();
    // The address is a multi-line text in the admin: show it on one line
    const address = useSetting('store.address', '')
        .trim()
        .replace(/\s*\n\s*/g, ', ');

    const parts = [legalName, address, vatNumber && t('footer_vat_number', { vat: vatNumber })].filter(Boolean);

    if (parts.length === 0) {
        return null;
    }

    return <p className="mt-2 text-sm text-gray-500">{parts.join(' · ')}</p>;
}

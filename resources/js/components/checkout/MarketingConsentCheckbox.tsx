import NewsletterConsentCheckbox from '@/components/newsletter/NewsletterConsentCheckbox';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';

interface MarketingConsentCheckboxProps {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
}

/** "**bold** plain" => alternating plain / bold parts (translations mark the emphasised words with **). */
function withBold(text: string) {
    return text.split('**').map((part, index) => (index % 2 === 1 ? <strong key={index}>{part}</strong> : part));
}

/**
 * Checkout marketing consent (unticked), posted as `marketing_consent`. Also covers the single abandoned-checkout
 * reminder, which the line under the label announces.
 */
export default function MarketingConsentCheckbox({ checked, onCheckedChange, disabled }: MarketingConsentCheckboxProps) {
    const { t } = useTranslation();
    const enabled = useSetting('newsletter.enabled', false);

    if (!enabled) {
        return null;
    }

    return (
        <div className="pt-4">
            <NewsletterConsentCheckbox id="marketing-consent" checked={checked} onCheckedChange={onCheckedChange} disabled={disabled}>
                <p className="text-sm text-gray-500">{withBold(t('newsletter.checkout_reminder'))}</p>
            </NewsletterConsentCheckbox>
        </div>
    );
}

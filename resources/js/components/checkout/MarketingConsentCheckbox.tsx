import NewsletterConsentCheckbox from '@/components/newsletter/NewsletterConsentCheckbox';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';

interface MarketingConsentCheckboxProps {
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
}

function withBold(text: string) {
    return text.split('**').map((part, index) => (index % 2 === 1 ? <strong key={index}>{part}</strong> : part));
}

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

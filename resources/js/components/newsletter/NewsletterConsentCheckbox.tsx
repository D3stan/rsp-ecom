import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { type ReactNode } from 'react';

interface NewsletterConsentCheckboxProps {
    id: string;
    checked: boolean;
    onCheckedChange: (checked: boolean) => void;
    disabled?: boolean;
    children?: ReactNode;
}

export default function NewsletterConsentCheckbox({ id, checked, onCheckedChange, disabled, children }: NewsletterConsentCheckboxProps) {
    const { t } = useTranslation();
    const enabled = useSetting('newsletter.enabled', false);

    if (!enabled) {
        return null;
    }

    return (
        <div className="flex items-start gap-3">
            <Checkbox id={id} checked={checked} onCheckedChange={(value) => onCheckedChange(value === true)} disabled={disabled} className="mt-0.5" />
            <div className="grid gap-1">
                <Label htmlFor={id} className="leading-snug font-normal">
                    {t('newsletter.consent_v1')}
                </Label>
                {children}
            </div>
        </div>
    );
}

import HeadingSmall from '@/components/heading-small';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import useTranslation from '@/hooks/useTranslation';
import { router } from '@inertiajs/react';
import { useState } from 'react';

export interface NewsletterPreferenceProps {
    newsletter: { status: 'none' | 'pending' | 'confirmed' | 'unsubscribed' } | null;
}

export default function NewsletterPreference({ newsletter }: NewsletterPreferenceProps) {
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);

    if (!newsletter) {
        return null;
    }

    const on = newsletter.status === 'pending' || newsletter.status === 'confirmed';

    const toggle = (subscribed: boolean) => {
        router.put(
            route('newsletter.preference'),
            { subscribed },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <div className="space-y-4">
            <HeadingSmall title={t('newsletter.account.title')} description={t('newsletter.account.description')} />

            <div className="flex items-start gap-3">
                <Switch id="newsletter-preference" checked={on} onCheckedChange={toggle} disabled={processing} className="mt-0.5" />
                <Label htmlFor="newsletter-preference" className="leading-snug font-normal">
                    {t('newsletter.consent_v1')}
                </Label>
            </div>

            {newsletter.status === 'pending' && <p className="text-sm text-muted-foreground">{t('newsletter.account.status_pending')}</p>}
            {newsletter.status === 'confirmed' && <p className="text-sm text-muted-foreground">{t('newsletter.account.status_confirmed')}</p>}
        </div>
    );
}

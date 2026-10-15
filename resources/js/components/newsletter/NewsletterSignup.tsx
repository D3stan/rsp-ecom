import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useSetting } from '@/hooks/use-setting';
import useTranslation from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { Link, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { type FormEventHandler, useState } from 'react';

interface NewsletterSignupProps {
    /** dark: storefront footer (black background); light: on a white page */
    variant?: 'dark' | 'light';
    className?: string;
}

/**
 * Newsletter sign-up form (double opt-in). Hidden while the newsletter.enabled setting is off.
 * The text above the field is the consent text whose version the server records (newsletter.consent_v1).
 * The server answers the same way for every address, so the confirmation message is always the same.
 */
export default function NewsletterSignup({ variant = 'dark', className }: NewsletterSignupProps) {
    const { t } = useTranslation();
    const enabled = useSetting('newsletter.enabled', false);
    const [sent, setSent] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({ email: '', source: 'footer', website: '' });

    if (!enabled) {
        return null;
    }

    const dark = variant === 'dark';

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('newsletter.subscribe'), {
            preserveScroll: true,
            onSuccess: () => {
                setSent(true);
                reset('email');
            },
        });
    };

    return (
        <div className={cn(dark ? 'text-white' : 'text-gray-900', className)}>
            <h4 className="mb-2 font-semibold">{t('newsletter.signup.title')}</h4>
            <p className={cn('mb-4 text-sm', dark ? 'text-gray-400' : 'text-gray-600')}>
                {t('newsletter.consent_v1')}{' '}
                <Link href="/privacy" className="underline underline-offset-2">
                    {t('newsletter.signup.privacy_link')}
                </Link>
            </p>

            {sent ? (
                <p role="status" className={cn('text-sm font-medium', dark ? 'text-green-400' : 'text-green-700')}>
                    {t('newsletter.check_inbox')}
                </p>
            ) : (
                <form onSubmit={submit} className="flex max-w-md flex-col gap-2 sm:flex-row" noValidate>
                    <label htmlFor="newsletter-email" className="sr-only">
                        {t('newsletter.signup.email_label')}
                    </label>
                    <Input
                        id="newsletter-email"
                        type="email"
                        name="email"
                        required
                        autoComplete="email"
                        inputMode="email"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        placeholder={t('newsletter.signup.email_placeholder')}
                        aria-invalid={errors.email ? true : undefined}
                        className={cn(dark && 'border-gray-700 bg-gray-900 text-white placeholder:text-gray-500')}
                    />
                    {/* Honeypot: hidden from people and assistive technology, filled in by bots */}
                    <div aria-hidden="true" className="absolute -left-[10000px] h-px w-px overflow-hidden">
                        <label htmlFor="newsletter-website">{t('newsletter.signup.honeypot_label')}</label>
                        <input
                            id="newsletter-website"
                            type="text"
                            name="website"
                            tabIndex={-1}
                            autoComplete="off"
                            value={data.website}
                            onChange={(e) => setData('website', e.target.value)}
                        />
                    </div>
                    <Button type="submit" disabled={processing} variant={dark ? 'secondary' : 'default'} className="shrink-0">
                        {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                        {t('newsletter.signup.button')}
                    </Button>
                </form>
            )}
            <InputError message={errors.email} className="mt-2" />
        </div>
    );
}

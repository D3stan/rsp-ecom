import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import { useForm } from '@inertiajs/react';
import { type FormEventHandler } from 'react';

interface CreateAccountOfferProps {
    name: string;
    email: string;
}

/**
 * On the success page of a guest buyer: one password turns this order's email into an account (the normal
 * registration, with the email verification). Orders placed with that email join the account once it is verified.
 */
export default function CreateAccountOffer({ name, email }: CreateAccountOfferProps) {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm({ name, email, password: '', password_confirmation: '' });

    const submit: FormEventHandler = (event) => {
        event.preventDefault();
        post(route('register'), { onFinish: () => reset('password', 'password_confirmation') });
    };

    return (
        <form onSubmit={submit} className="rounded-lg border border-gray-200 bg-gray-50 p-4 text-black">
            <p className="font-medium">{t('checkout.success.create_account_title')}</p>
            <p className="mt-1 text-sm text-gray-600">{t('checkout.success.create_account_body', { email })}</p>

            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <div className="grid gap-1">
                    <Label htmlFor="offer-password">{t('auth.password')}</Label>
                    <Input
                        id="offer-password"
                        type="password"
                        autoComplete="new-password"
                        value={data.password}
                        onChange={(e) => setData('password', e.target.value)}
                        required
                    />
                    <InputError message={errors.password} />
                </div>
                <div className="grid gap-1">
                    <Label htmlFor="offer-password-confirmation">{t('auth.confirm_password_label')}</Label>
                    <Input
                        id="offer-password-confirmation"
                        type="password"
                        autoComplete="new-password"
                        value={data.password_confirmation}
                        onChange={(e) => setData('password_confirmation', e.target.value)}
                        required
                    />
                </div>
            </div>
            {/* the email is registered already, or not valid for a new account */}
            <InputError message={errors.email} className="mt-2" />
            <Button type="submit" className="mt-3 bg-black text-white hover:bg-gray-800" disabled={processing}>
                {t('auth.register.submit')}
            </Button>
        </form>
    );
}

// Components
import { Head, useForm } from '@inertiajs/react';
import { LoaderCircle } from 'lucide-react';
import { FormEventHandler } from 'react';

import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import useTranslation from '@/hooks/useTranslation';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const { t } = useTranslation();
    const { data, setData, post, processing, errors, reset } = useForm<Required<{ password: string }>>({
        password: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('password.confirm'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <AuthLayout title={t('auth.confirm.title')} description={t('auth.confirm.description')}>
            <Head title={t('auth.confirm.head')} />

            <form onSubmit={submit}>
                <div className="space-y-6">
                    <div className="grid gap-2">
                        <Label htmlFor="password">{t('auth.password')}</Label>
                        <Input
                            id="password"
                            type="password"
                            name="password"
                            placeholder={t('auth.password_placeholder')}
                            autoComplete="current-password"
                            value={data.password}
                            autoFocus
                            onChange={(e) => setData('password', e.target.value)}
                        />

                        <InputError message={errors.password} />
                    </div>

                    <div className="flex items-center">
                        <Button className="w-full" disabled={processing}>
                            {processing && <LoaderCircle className="h-4 w-4 animate-spin" />}
                            {t('auth.confirm.submit')}
                        </Button>
                    </div>
                </div>
            </form>
        </AuthLayout>
    );
}

import HeadingSmall from '@/components/heading-small';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import useTranslation from '@/hooks/useTranslation';
import { type SharedData } from '@/types';
import { Transition } from '@headlessui/react';
import { router, usePage } from '@inertiajs/react';
import { useState } from 'react';

/** Account setting: back-in-stock emails for items in the cart or wishlist (users.notify_back_in_stock). */
export default function BackInStockPreference() {
    const { auth } = usePage<SharedData>().props;
    const { t } = useTranslation();
    const [processing, setProcessing] = useState(false);
    const [saved, setSaved] = useState(false);

    const enabled = auth.user?.notify_back_in_stock !== false;

    const update = (value: boolean) => {
        setSaved(false);
        router.patch(
            route('notification-preferences.update'),
            { notify_back_in_stock: value },
            {
                preserveScroll: true,
                onStart: () => setProcessing(true),
                onFinish: () => setProcessing(false),
                onSuccess: () => setSaved(true),
            },
        );
    };

    return (
        <div className="space-y-6">
            <HeadingSmall title={t('restock.preference_title')} description={t('restock.preference_description')} />

            <div className="flex items-center justify-between gap-4">
                <Label htmlFor="notify_back_in_stock" className="leading-snug font-normal">
                    {t('restock.preference_label')}
                </Label>
                <Switch id="notify_back_in_stock" checked={enabled} disabled={processing} onCheckedChange={update} />
            </div>

            <Transition show={saved} enter="transition ease-in-out" enterFrom="opacity-0" leave="transition ease-in-out" leaveTo="opacity-0">
                <p className="text-sm text-neutral-600">{t('restock.saved')}</p>
            </Transition>
        </div>
    );
}

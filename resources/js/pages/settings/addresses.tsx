import AddressForm, { type AddressEntry } from '@/components/address/AddressForm';
import HeadingSmall from '@/components/heading-small';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import useTranslation from '@/hooks/useTranslation';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import { useState } from 'react';

interface AddressesProps {
    addresses: AddressEntry[];
    provinces: Record<string, string>;
    max: number;
}

export default function Addresses({ addresses, provinces, max }: AddressesProps) {
    const { t } = useTranslation();
    // 'new', the id of the entry being edited, or null
    const [editing, setEditing] = useState<'new' | number | null>(null);
    const [deleting, setDeleting] = useState<AddressEntry | null>(null);

    const breadcrumbs: BreadcrumbItem[] = [{ title: t('account.addresses.title'), href: '/settings/addresses' }];

    const makeDefault = (address: AddressEntry) =>
        router.put(route('addresses.update', address.id), { ...address, is_default: true }, { preserveScroll: true });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('account.addresses.title')} />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title={t('account.addresses.title')} description={t('account.addresses.description')} />

                    {addresses.length === 0 && editing !== 'new' && <p className="text-sm text-muted-foreground">{t('account.addresses.empty')}</p>}

                    <ul className="space-y-4">
                        {addresses.map((address) => (
                            <li key={address.id}>
                                {editing === address.id ? (
                                    <AddressForm address={address} provinces={provinces} onDone={() => setEditing(null)} />
                                ) : (
                                    <div className="rounded-lg border p-4">
                                        <div className="flex items-start justify-between gap-2">
                                            <address className="text-sm not-italic">
                                                <p className="font-medium">
                                                    {address.first_name} {address.last_name}
                                                </p>
                                                {address.company && <p>{address.company}</p>}
                                                <p>{address.address_line_1}</p>
                                                {address.address_line_2 && <p>{address.address_line_2}</p>}
                                                <p>
                                                    {address.postal_code} {address.city} ({address.state}) · {address.country}
                                                </p>
                                                {address.phone && <p>{address.phone}</p>}
                                            </address>
                                            {address.is_default && <Badge>{t('account.addresses.default')}</Badge>}
                                        </div>
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            <Button size="sm" variant="outline" onClick={() => setEditing(address.id)}>
                                                {t('account.addresses.edit')}
                                            </Button>
                                            {!address.is_default && (
                                                <Button size="sm" variant="outline" onClick={() => makeDefault(address)}>
                                                    {t('account.addresses.set_default')}
                                                </Button>
                                            )}
                                            <Button size="sm" variant="ghost" onClick={() => setDeleting(address)}>
                                                {t('account.addresses.delete')}
                                            </Button>
                                        </div>
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>

                    {editing === 'new' ? (
                        <AddressForm provinces={provinces} onDone={() => setEditing(null)} />
                    ) : (
                        <Button onClick={() => setEditing('new')} disabled={addresses.length >= max}>
                            {t('account.addresses.add')}
                        </Button>
                    )}
                    {addresses.length >= max && <p className="text-sm text-muted-foreground">{t('account.addresses.limit_reached', { max })}</p>}
                </div>

                <AlertDialog open={deleting !== null} onOpenChange={(open) => !open && setDeleting(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>{t('account.addresses.delete_title')}</AlertDialogTitle>
                            <AlertDialogDescription>
                                {deleting && `${deleting.first_name} ${deleting.last_name}, ${deleting.address_line_1}, ${deleting.city}`}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>{t('account.addresses.cancel')}</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={() => deleting && router.delete(route('addresses.destroy', deleting.id), { preserveScroll: true })}
                            >
                                {t('account.addresses.delete')}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </SettingsLayout>
        </AppLayout>
    );
}

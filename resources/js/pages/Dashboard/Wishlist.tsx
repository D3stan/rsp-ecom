import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { WishlistCard } from '@/components/wishlist-card';
import AppLayout from '@/layouts/app-layout';
import useTranslation from '@/hooks/useTranslation';
import { type BreadcrumbItem, type Wishlist } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { 
    Heart, 
    ShoppingCart, 
    ArrowLeft,
    Plus
} from 'lucide-react';
import React from 'react';

interface WishlistPageProps {
    wishlist: Wishlist[];
}

const WishlistPage: React.FC<WishlistPageProps> = ({ wishlist }) => {
    const { t } = useTranslation();

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: t('dashboard.title'),
            href: route('dashboard'),
        },
        {
            title: t('wishlist.title'),
            href: route('wishlist.index'),
        },
    ];
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('wishlist.page_title')} />

            <div className="space-y-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <div className="flex items-center gap-2 mb-2">
                            <Button variant="ghost" size="sm" asChild>
                                <Link href={route('dashboard')}>
                                    <ArrowLeft className="h-4 w-4" />
                                </Link>
                            </Button>
                            <h1 className="text-2xl font-bold tracking-tight flex items-center gap-2">
                                <Heart className="h-6 w-6" />
                                {t('wishlist.title')}
                            </h1>
                        </div>
                        <p className="text-muted-foreground">
                            {wishlist.length === 0 
                                ? t('wishlist.empty_title')
                                : t('wishlist.items_saved', { 
                                    count: wishlist.length, 
                                    item_word: wishlist.length === 1 ? t('wishlist.item') : t('wishlist.items')
                                })
                            }
                        </p>
                    </div>
                    
                    {wishlist.length > 0 && (
                        <div className="flex gap-2">
                            <Button variant="outline" asChild>
                                <Link href={route('products')}>
                                    <Plus className="h-4 w-4 mr-2" />
                                    {t('wishlist.add_more_items')}
                                </Link>
                            </Button>
                        </div>
                    )}
                </div>

                {/* Wishlist Items */}
                {wishlist.length === 0 ? (
                    <Card>
                        <CardContent className="pt-6">
                            <div className="text-center py-12">
                                <Heart className="mx-auto h-16 w-16 text-muted-foreground" />
                                <h3 className="mt-4 text-xl font-medium">{t('wishlist.empty_title')}</h3>
                                <p className="mt-2 text-muted-foreground max-w-md mx-auto">
                                    {t('wishlist.empty_description')}
                                </p>
                                <div className="mt-8">
                                    <Button size="lg" asChild>
                                        <Link href={route('products')}>
                                            <ShoppingCart className="h-4 w-4 mr-2" />
                                            {t('wishlist.start_shopping')}
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid gap-6 sm:grid-cols-1 lg:grid-cols-2 xl:grid-cols-3">
                        {wishlist.map((item) => (
                            <WishlistCard 
                                key={item.id} 
                                item={item}
                                variant="detailed"
                                showActions={true}
                            />
                        ))}
                    </div>
                )}

                {/* Continue Shopping */}
                {wishlist.length > 0 && (
                    <div className="flex justify-center pt-6">
                        <Button variant="outline" size="lg" asChild>
                            <Link href={route('products')}>
                                <Plus className="h-4 w-4 mr-2" />
                                {t('wishlist.continue_shopping')}
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
};

export default WishlistPage;

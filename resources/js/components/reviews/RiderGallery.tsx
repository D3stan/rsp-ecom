import { useTranslation } from '@/hooks/useTranslation';
import { Link } from '@inertiajs/react';

export interface RiderGalleryPhoto {
    id: number;
    thumbnail_url: string;
    url: string;
    width: number;
    height: number;
    product: {
        name: string;
        slug: string;
    };
}

interface RiderGalleryProps {
    photos: RiderGalleryPhoto[];
}

export default function RiderGallery({ photos }: RiderGalleryProps) {
    const { t } = useTranslation();

    if (photos.length === 0) return null;

    return (
        <section className="relative z-10 bg-white py-16 lg:py-24">
            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div className="mb-12 text-center">
                    <h2 className="mb-4 text-3xl font-bold text-black">{t('home.rider_gallery.title')}</h2>
                    <p className="mx-auto max-w-2xl text-gray-600">{t('home.rider_gallery.description')}</p>
                </div>

                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
                    {photos.map((photo) => (
                        <li key={photo.id}>
                            <Link
                                href={`/products/${photo.product.slug}`}
                                className="group block aspect-square overflow-hidden rounded-xl bg-gray-100"
                                aria-label={t('home.rider_gallery.view_product', { name: photo.product.name })}
                            >
                                <img
                                    src={photo.thumbnail_url}
                                    alt={t('home.rider_gallery.photo_alt', { name: photo.product.name })}
                                    width={photo.width}
                                    height={photo.height}
                                    loading="lazy"
                                    decoding="async"
                                    className="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                            </Link>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}

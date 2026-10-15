import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useTranslation } from '@/hooks/useTranslation';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useCallback, useEffect } from 'react';
import type { GalleryPhoto } from './review-gallery';

interface ReviewLightboxProps {
    photos: GalleryPhoto[];
    /** Index of the open photo, or null when the lightbox is closed. */
    index: number | null;
    onIndexChange: (index: number | null) => void;
}

/** Full-size viewer for review photos: a shadcn Dialog with labelled, keyboard-navigable prev/next controls. */
export default function ReviewLightbox({ photos, index, onIndexChange }: ReviewLightboxProps) {
    const { t } = useTranslation();
    const open = index !== null;
    const photo = open ? photos[index] : undefined;

    const go = useCallback(
        (delta: number) => {
            if (index === null || photos.length === 0) return;
            onIndexChange((index + delta + photos.length) % photos.length);
        },
        [index, photos.length, onIndexChange],
    );

    useEffect(() => {
        if (!open) return;

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'ArrowRight') {
                event.preventDefault();
                go(1);
            } else if (event.key === 'ArrowLeft') {
                event.preventDefault();
                go(-1);
            }
        };

        document.addEventListener('keydown', onKey);
        return () => document.removeEventListener('keydown', onKey);
    }, [open, go]);

    if (!photo) return null;

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onIndexChange(null)}>
            <DialogContent className="max-w-3xl border-0 bg-transparent p-0 shadow-none">
                <DialogTitle className="sr-only">
                    {t('product.reviews_block.photo_by', { name: photo.reviewAuthor })} ({index! + 1}/{photos.length})
                </DialogTitle>

                <div className="relative flex items-center justify-center">
                    <img
                        src={photo.url}
                        alt={t('product.reviews_block.photo_by', { name: photo.reviewAuthor })}
                        width={photo.width}
                        height={photo.height}
                        className="max-h-[80vh] w-auto rounded-lg object-contain"
                    />

                    {photos.length > 1 && (
                        <>
                            <button
                                type="button"
                                onClick={() => go(-1)}
                                aria-label={t('product.reviews_block.previous_photo')}
                                className="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-lg transition-colors hover:bg-white focus:ring-2 focus:ring-black focus:outline-none"
                            >
                                <ChevronLeft className="h-6 w-6" aria-hidden="true" />
                            </button>
                            <button
                                type="button"
                                onClick={() => go(1)}
                                aria-label={t('product.reviews_block.next_photo')}
                                className="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-white/80 p-2 shadow-lg transition-colors hover:bg-white focus:ring-2 focus:ring-black focus:outline-none"
                            >
                                <ChevronRight className="h-6 w-6" aria-hidden="true" />
                            </button>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

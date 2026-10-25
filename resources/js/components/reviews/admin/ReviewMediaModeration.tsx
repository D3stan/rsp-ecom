import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Switch } from '@/components/ui/switch';
import { useTranslation } from '@/hooks/useTranslation';
import { router } from '@inertiajs/react';
import { Check, CheckCheck, X } from 'lucide-react';
import { useId, useState } from 'react';

export interface AdminReviewMedia {
    id: number;
    url: string;
    thumbnail_url: string;
    width: number;
    height: number;
    status: 'pending' | 'approved' | 'rejected';
    featured: boolean;
}

interface Props {
    reviewId: number;
    reviewApproved: boolean;
    media: AdminReviewMedia[];
    compact?: boolean;
}

const statusClasses: Record<AdminReviewMedia['status'], string> = {
    pending: 'bg-yellow-100 text-yellow-800',
    approved: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
};

export default function ReviewMediaModeration({ reviewId, reviewApproved, media, compact = false }: Props) {
    const { t } = useTranslation();
    const idPrefix = useId();
    const [busy, setBusy] = useState(false);

    if (media.length === 0) return null;

    const pending = media.filter((item) => item.status === 'pending').length;

    const patch = (url: string, data: Record<string, boolean> = {}) => {
        setBusy(true);
        router.patch(url, data, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    const mediaUrl = (action: 'approve' | 'reject' | 'feature', mediaId: number) =>
        route(`admin.reviews.media.${action}`, { review: reviewId, media: mediaId });

    return (
        <div className="space-y-3">
            {!compact && (
                <div>
                    <h2 className="text-lg font-semibold">{t('reviews.admin.photos_title')}</h2>
                    <p className="text-sm text-muted-foreground">{t('reviews.admin.photos_hint')}</p>
                </div>
            )}
            {compact && <p className="text-xs text-muted-foreground">{t('reviews.admin.photo_count', { count: media.length, pending })}</p>}

            <ul className={compact ? 'flex flex-wrap gap-2' : 'grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4'}>
                {media.map((item, index) => {
                    const n = index + 1;
                    const switchId = `${idPrefix}-feature-${item.id}`;

                    return (
                        <li key={item.id} className={compact ? 'w-24 space-y-1' : 'space-y-2 rounded-lg border p-2'}>
                            <a
                                href={item.url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="block overflow-hidden rounded-md bg-muted"
                                aria-label={t('reviews.admin.open_full', { n })}
                            >
                                <img
                                    src={item.thumbnail_url}
                                    alt={t('reviews.admin.photo_alt', { n })}
                                    width={item.width}
                                    height={item.height}
                                    loading="lazy"
                                    className={`${compact ? 'h-24' : 'h-40'} w-full object-cover ${item.status === 'rejected' ? 'opacity-40' : ''}`}
                                />
                            </a>
                            <Badge variant="secondary" className={statusClasses[item.status]}>
                                {t(`reviews.admin.status.${item.status}`)}
                            </Badge>
                            <div className="flex gap-1">
                                {item.status !== 'approved' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 px-2 text-green-700 hover:bg-green-50"
                                        disabled={busy}
                                        onClick={() => patch(mediaUrl('approve', item.id))}
                                        aria-label={t('reviews.admin.approve_photo_n', { n })}
                                        title={t('reviews.admin.approve')}
                                    >
                                        <Check className="h-4 w-4" aria-hidden="true" />
                                        {!compact && <span className="ml-1">{t('reviews.admin.approve')}</span>}
                                    </Button>
                                )}
                                {item.status !== 'rejected' && (
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="ghost"
                                        className="h-7 px-2 text-red-700 hover:bg-red-50"
                                        disabled={busy}
                                        onClick={() => patch(mediaUrl('reject', item.id))}
                                        aria-label={t('reviews.admin.reject_photo_n', { n })}
                                        title={t('reviews.admin.reject')}
                                    >
                                        <X className="h-4 w-4" aria-hidden="true" />
                                        {!compact && <span className="ml-1">{t('reviews.admin.reject')}</span>}
                                    </Button>
                                )}
                            </div>
                            {!compact && item.status === 'approved' && (
                                <div className="flex items-center gap-2">
                                    <Switch
                                        id={switchId}
                                        checked={item.featured}
                                        disabled={busy}
                                        onCheckedChange={(checked) => patch(mediaUrl('feature', item.id), { featured: checked })}
                                        aria-label={t('reviews.admin.feature_photo_n', { n })}
                                    />
                                    <label htmlFor={switchId} className="text-xs">
                                        {t('reviews.admin.feature')}
                                    </label>
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>

            {(!reviewApproved || pending > 0) && (
                <Button
                    type="button"
                    size="sm"
                    variant={compact ? 'outline' : 'default'}
                    className={compact ? 'h-7' : 'bg-green-600 hover:bg-green-700'}
                    disabled={busy}
                    onClick={() => patch(route('admin.reviews.approve-with-media', reviewId))}
                >
                    <CheckCheck className="mr-1 h-4 w-4" aria-hidden="true" />
                    {t('reviews.admin.approve_with_photos')}
                </Button>
            )}
        </div>
    );
}

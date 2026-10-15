import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/hooks/useTranslation';
import { ImagePlus, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';
import { addPhotos, formatBytes, PHOTO_TYPES, type PhotoLimits, type PhotoProblem } from './review-photos';

interface ReviewPhotoInputProps {
    value: File[];
    onChange: (files: File[]) => void;
    limits: PhotoLimits;
    /** Server-side errors for `photos` and `photos.N` */
    errors?: string[];
    disabled?: boolean;
}

/** Up to `limits.max_files` photos with previews and remove buttons (review form). */
export default function ReviewPhotoInput({ value, onChange, limits, errors = [], disabled = false }: ReviewPhotoInputProps) {
    const { t, locale } = useTranslation();
    const inputId = useId();
    const helpId = `${inputId}-help`;
    const inputRef = useRef<HTMLInputElement>(null);
    const [problems, setProblems] = useState<PhotoProblem[]>([]);
    const [previews, setPreviews] = useState<string[]>([]);

    // Object URLs for the previews, released when the selection changes or the form unmounts
    useEffect(() => {
        const urls = value.map((file) => URL.createObjectURL(file));
        setPreviews(urls);

        return () => urls.forEach((url) => URL.revokeObjectURL(url));
    }, [value]);

    const size = formatBytes(limits.max_file_size, locale);

    const describe = (problem: PhotoProblem): string => {
        switch (problem.key) {
            case 'too_many':
                return t('reviews.photos.too_many_client', { max: limits.max_files });
            case 'wrong_type':
                return t('reviews.photos.wrong_type_client', { name: problem.name });
            case 'too_large':
                return t('reviews.photos.too_large_client', { name: problem.name, size });
            case 'total_too_large':
                return t('reviews.photos.total_too_large', { size: formatBytes(limits.max_request_size, locale) });
        }
    };

    const handlePick = (event: React.ChangeEvent<HTMLInputElement>) => {
        const picked = Array.from(event.target.files ?? []);
        // Reset, so picking the same file again after removing it still fires a change
        event.target.value = '';

        const result = addPhotos(value, picked, limits);
        setProblems(result.problems);
        onChange(result.files);
    };

    const remove = (index: number) => {
        setProblems([]);
        onChange(value.filter((_, i) => i !== index));
        inputRef.current?.focus();
    };

    const messages = [...problems.map(describe), ...errors];

    return (
        <div className="space-y-2">
            <Label htmlFor={inputId}>{t('reviews.photos.label')}</Label>
            <p id={helpId} className="text-xs text-muted-foreground">
                {t('reviews.photos.help', { max: limits.max_files, size })}
            </p>

            {value.length > 0 && (
                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {value.map((file, index) => (
                        <li
                            key={`${file.name}-${file.lastModified}-${index}`}
                            className="relative aspect-square overflow-hidden rounded-md border bg-muted"
                        >
                            {previews[index] && (
                                <img
                                    src={previews[index]}
                                    alt={t('reviews.photos.preview_alt', { n: index + 1 })}
                                    className="h-full w-full object-cover"
                                />
                            )}
                            <Button
                                type="button"
                                variant="secondary"
                                size="icon"
                                className="absolute top-1 right-1 h-7 w-7 rounded-full shadow"
                                onClick={() => remove(index)}
                                disabled={disabled}
                                aria-label={t('reviews.photos.remove', { n: index + 1 })}
                            >
                                <X className="h-4 w-4" aria-hidden="true" />
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            <input
                ref={inputRef}
                id={inputId}
                type="file"
                accept={PHOTO_TYPES.join(',')}
                multiple
                className="sr-only"
                onChange={handlePick}
                disabled={disabled || value.length >= limits.max_files}
                aria-describedby={helpId}
            />
            {value.length < limits.max_files && (
                <Button type="button" variant="outline" onClick={() => inputRef.current?.click()} disabled={disabled}>
                    <ImagePlus className="mr-2 h-4 w-4" aria-hidden="true" />
                    {t('reviews.photos.add')}
                </Button>
            )}

            {messages.length > 0 && (
                <ul role="alert" className="space-y-1 text-sm text-red-600">
                    {messages.map((message, index) => (
                        <li key={index}>{message}</li>
                    ))}
                </ul>
            )}
        </div>
    );
}

import { useIsMobile } from '@/hooks/use-mobile';
import { useTranslation } from '@/hooks/useTranslation';
import { cn } from '@/lib/utils';
import { useCallback, useId, useLayoutEffect, useRef, useState } from 'react';

// About 6 lines of body text
const COLLAPSED_HEIGHT_PX = 156;

interface RichTextProps {
    /** Sanitized HTML from the server (MarkdownRenderer): the backend is the only place that decides what is allowed. */
    html: string;
    /** On mobile, fold content taller than about 6 lines behind a read-more button. */
    collapsible?: boolean;
    className?: string;
}

/**
 * The single place where the storefront prints server-rendered description HTML.
 */
export default function RichText({ html, collapsible = false, className }: RichTextProps) {
    const { t } = useTranslation();
    const isMobile = useIsMobile();
    const contentId = useId();
    const ref = useRef<HTMLDivElement>(null);
    const [expanded, setExpanded] = useState(false);
    const [overflows, setOverflows] = useState(false);

    const measure = useCallback(() => {
        if (ref.current) {
            setOverflows(ref.current.scrollHeight > COLLAPSED_HEIGHT_PX + 8);
        }
    }, []);

    useLayoutEffect(() => {
        setExpanded(false);
        measure();
    }, [html, measure]);

    const canCollapse = collapsible && isMobile && overflows;

    return (
        <div className={className}>
            <div
                id={contentId}
                ref={ref}
                // Images that finish loading later change the height
                onLoadCapture={measure}
                style={canCollapse && !expanded ? { maxHeight: COLLAPSED_HEIGHT_PX } : undefined}
                className={cn(
                    'leading-relaxed break-words text-gray-700 [&>:first-child]:mt-0',
                    '[&_a]:underline [&_a]:underline-offset-2',
                    '[&_img]:my-3 [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-md',
                    '[&_ol]:my-2 [&_ol]:list-decimal [&_ol]:pl-5 [&_p]:my-2 [&_ul]:my-2 [&_ul]:list-disc [&_ul]:pl-5',
                    '[&_h3]:mt-4 [&_h3]:text-lg [&_h3]:font-semibold [&_h4]:mt-3 [&_h4]:font-semibold [&_h5]:mt-3 [&_h5]:font-semibold [&_h6]:mt-3 [&_h6]:font-semibold',
                    '[&_blockquote]:border-l-2 [&_blockquote]:border-gray-300 [&_blockquote]:pl-3 [&_blockquote]:text-gray-500',
                    canCollapse && !expanded && 'overflow-hidden',
                )}
                dangerouslySetInnerHTML={{ __html: html }}
            />
            {canCollapse && (
                <button
                    type="button"
                    aria-expanded={expanded}
                    aria-controls={contentId}
                    onClick={() => setExpanded((open) => !open)}
                    className="mt-2 text-sm font-medium underline underline-offset-2"
                >
                    {expanded ? t('product.read_less') : t('product.read_more')}
                </button>
            )}
        </div>
    );
}

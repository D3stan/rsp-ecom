import { useCallback, useState } from 'react';
import { useIsMobile } from './use-mobile';

export function useMobileInteractions<T extends string | number>() {
    const [activeId, setActiveId] = useState<T | null>(null);
    const isMobile = useIsMobile();

    const handleInteraction = useCallback(
        (id: T, callback?: () => void) => {
            if (!isMobile) {
                callback?.();
                return;
            }

            if (activeId === id) {
                // Second tap - execute callback
                setActiveId(null);
                callback?.();
            } else {
                // First tap - show active state
                setActiveId(id);
                // Auto-reset after 3 seconds
                setTimeout(() => setActiveId(null), 3000);
            }
        },
        [isMobile, activeId],
    );

    const isActive = useCallback(
        (id: T) => {
            return isMobile ? activeId === id : false;
        },
        [isMobile, activeId],
    );

    const getInteractionProps = useCallback(
        (id: T, callback?: () => void) => {
            if (!isMobile) {
                return { onClick: callback };
            }

            return {
                onClick: (e: React.MouseEvent) => {
                    e.preventDefault();
                    handleInteraction(id, callback);
                },
            };
        },
        [isMobile, handleInteraction],
    );

    return {
        isActive,
        handleInteraction,
        getInteractionProps,
        isMobile,
    };
}

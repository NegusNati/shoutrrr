import { useEffect, useRef } from 'react';

/**
 * Scroll sentinel for paged streams: when the returned element scrolls into
 * view inside the nearest scrollable ancestor, `onVisible` fires (typically
 * `fetchNextPage`). Renders as a zero-height div placed after the list.
 */
export function useInfiniteScroll(onVisible: () => void, enabled: boolean) {
    const sentinelRef = useRef<HTMLDivElement>(null);
    // Latest-callback ref so the observer doesn't reattach on every render.
    const onVisibleRef = useRef(onVisible);
    onVisibleRef.current = onVisible;

    useEffect(() => {
        const sentinel = sentinelRef.current;
        if (!enabled || sentinel === null) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries[0]?.isIntersecting) {
                onVisibleRef.current();
            }
        });
        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [enabled]);

    return sentinelRef;
}

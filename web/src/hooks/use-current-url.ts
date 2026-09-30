import { useLocation } from '@tanstack/react-router';

import { toUrl } from '@/lib/href';
import type { Href } from '@/lib/href';

export type IsCurrentUrlFn = (
    urlToCheck: Href,
    currentUrl?: string,
    startsWith?: boolean,
) => boolean;

export type IsCurrentOrParentUrlFn = (
    urlToCheck: Href,
    currentUrl?: string,
) => boolean;

export type WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
    urlToCheck: Href,
    ifTrue: TIfTrue,
    ifFalse?: TIfFalse,
) => TIfTrue | TIfFalse;

export type UseCurrentUrlReturn = {
    currentUrl: string;
    isCurrentUrl: IsCurrentUrlFn;
    isCurrentOrParentUrl: IsCurrentOrParentUrlFn;
    whenCurrentUrl: WhenCurrentUrlFn;
};

/**
 * Location helpers for nav highlighting. The SPA mounts under `/app`, so
 * `currentUrl` is reported relative to that base to keep comparisons with
 * legacy (`/dashboard`) style URLs working.
 */
export function useCurrentUrl(): UseCurrentUrlReturn {
    const pathname = useLocation({ select: (l) => l.pathname });
    const currentUrlPath = pathname.replace(/^\/app(?=\/|$)/, '') || '/';

    const isCurrentUrl: IsCurrentUrlFn = (
        urlToCheck: Href,
        currentUrl?: string,
        startsWith: boolean = false,
    ) => {
        const urlToCompare = currentUrl ?? currentUrlPath;
        const urlString = toUrl(urlToCheck);

        const comparePath = (path: string): boolean =>
            startsWith ? urlToCompare.startsWith(path) : path === urlToCompare;

        if (!urlString.startsWith('http')) {
            return comparePath(urlString);
        }

        try {
            const absoluteUrl = new URL(urlString);

            return comparePath(absoluteUrl.pathname);
        } catch {
            return false;
        }
    };

    const isCurrentOrParentUrl: IsCurrentOrParentUrlFn = (
        urlToCheck: Href,
        currentUrl?: string,
    ) => {
        const urlString = toUrl(urlToCheck);

        return isCurrentUrl(urlString, currentUrl, true);
    };

    const whenCurrentUrl: WhenCurrentUrlFn = <TIfTrue, TIfFalse = null>(
        urlToCheck: Href,
        ifTrue: TIfTrue,
        ifFalse: TIfFalse = null as TIfFalse,
    ): TIfTrue | TIfFalse => {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    };

    return {
        currentUrl: currentUrlPath,
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
    };
}

import { useEffect } from 'react';

/**
 * `Inertia <Head title>` equivalent: `${title} - ${appName}` while mounted,
 * restored on unmount. Call at the top of each page component.
 */
export function useDocumentTitle(title?: string) {
    const appName = import.meta.env.VITE_APP_NAME ?? 'Shoutrrr';
    useEffect(() => {
        const previous = document.title;
        document.title = title ? `${title} - ${appName}` : appName;
        return () => {
            document.title = previous;
        };
    }, [title, appName]);
}

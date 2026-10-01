import { useQuery } from '@tanstack/react-query';
import { createFileRoute } from '@tanstack/react-router';
import { useEffect } from 'react';

import { publicShareQuery } from '@/features/share/share';
import { useDocumentTitle } from '@/hooks/use-document-title';
import ShareShow from '@/pages/share/show';

export const Route = createFileRoute('/share/$token')({
    component: ShareRoute,
});

function ShareRoute() {
    const { token } = Route.useParams();
    const { data, isPending } = useQuery(publicShareQuery(token));

    // Public share pages must not be indexed (mirrors the legacy noindex).
    useEffect(() => {
        const meta = document.createElement('meta');
        meta.name = 'robots';
        meta.content = 'noindex, nofollow';
        document.head.appendChild(meta);
        return () => {
            meta.remove();
        };
    }, []);

    useDocumentTitle('Shared post');

    if (isPending || data === undefined) {
        return <div className="min-h-screen bg-background" />;
    }

    return <ShareShow post={data.post} />;
}

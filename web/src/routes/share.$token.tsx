import { createFileRoute } from '@tanstack/react-router';

import { shareQuery } from '@/features/share/share';
import ShareShow from '@/pages/share';

/**
 * Public share link — lives outside the authed `_app` layout so guests can
 * open it. The legacy /share/{token} web route redirects here.
 */
export const Route = createFileRoute('/share/$token')({
    loader: ({ context: { queryClient }, params: { token } }) =>
        queryClient.ensureQueryData(shareQuery(token)),
    component: ShareRoute,
});

function ShareRoute() {
    const { token } = Route.useParams();

    return <ShareShow token={token} />;
}

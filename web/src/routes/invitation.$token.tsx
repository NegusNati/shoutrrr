import { createFileRoute } from '@tanstack/react-router';

import WorkspaceInvitationPage from '@/pages/invitation';

/**
 * Public invitation accept page — outside `_app` so guests can view it; the
 * page itself auto-accepts when a session is present. The legacy
 * /invitation/{token} web route redirects here.
 */
export const Route = createFileRoute('/invitation/$token')({
    component: InvitationRoute,
});

function InvitationRoute() {
    const { token } = Route.useParams();

    return <WorkspaceInvitationPage token={token} />;
}

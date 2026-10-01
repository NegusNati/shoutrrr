import { createFileRoute } from '@tanstack/react-router';

import AuthLayout from '@/layouts/auth-layout';
import WorkspaceInvitationPage from '@/pages/auth/workspace-invitation';

export const Route = createFileRoute('/invitation/$token')({
    component: InvitationRoute,
});

function InvitationRoute() {
    const { token } = Route.useParams();

    return (
        <AuthLayout
            title="You're invited"
            description="Accept your invitation to join the workspace"
            brandText="Shoutrrr"
        >
            <WorkspaceInvitationPage token={token} />
        </AuthLayout>
    );
}

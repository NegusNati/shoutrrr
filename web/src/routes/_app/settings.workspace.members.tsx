import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSettingsLayout from '@/layouts/workspace-settings-layout';
import WorkspaceMembersPage from '@/pages/settings/workspace/members';

export const Route = createFileRoute('/_app/settings/workspace/members')({
    component: WorkspaceMembersRoute,
});

function WorkspaceMembersRoute() {
    return (
        <WorkspaceSettingsLayout>
            <WorkspaceMembersPage />
        </WorkspaceSettingsLayout>
    );
}

import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSettingsLayout from '@/layouts/workspace-settings-layout';
import WorkspaceOverviewPage from '@/pages/settings/workspace/overview';

export const Route = createFileRoute('/_app/settings/workspace/')({
    component: WorkspaceOverviewRoute,
});

function WorkspaceOverviewRoute() {
    return (
        <WorkspaceSettingsLayout>
            <WorkspaceOverviewPage />
        </WorkspaceSettingsLayout>
    );
}

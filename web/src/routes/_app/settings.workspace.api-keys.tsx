import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSettingsLayout from '@/layouts/workspace-settings-layout';
import ApiKeysPage from '@/pages/settings/workspace/api-keys';

export const Route = createFileRoute('/_app/settings/workspace/api-keys')({
    component: ApiKeysRoute,
});

function ApiKeysRoute() {
    return (
        <WorkspaceSettingsLayout>
            <ApiKeysPage />
        </WorkspaceSettingsLayout>
    );
}

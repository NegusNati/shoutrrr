import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSettingsLayout from '@/layouts/workspace-settings-layout';
import SubscriptionPage from '@/pages/settings/workspace/subscription';

export const Route = createFileRoute('/_app/settings/workspace/subscription')({
    component: SubscriptionRoute,
});

function SubscriptionRoute() {
    return (
        <WorkspaceSettingsLayout>
            <SubscriptionPage />
        </WorkspaceSettingsLayout>
    );
}

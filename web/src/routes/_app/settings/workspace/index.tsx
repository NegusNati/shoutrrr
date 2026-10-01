import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSettingsOverview from '@/pages/settings/workspace/overview';

export const Route = createFileRoute('/_app/settings/workspace/')({
    component: WorkspaceSettingsOverview,
});

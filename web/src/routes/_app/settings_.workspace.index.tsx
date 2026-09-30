import { createFileRoute } from '@tanstack/react-router';

import WorkspaceOverview from '@/pages/settings/workspace/overview';

export const Route = createFileRoute('/_app/settings_/workspace/')({
    component: WorkspaceOverview,
});

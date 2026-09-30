import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSettingsLayout from '@/layouts/workspace-settings-layout';

export const Route = createFileRoute('/_app/settings_/workspace')({
    component: WorkspaceSettingsLayout,
});

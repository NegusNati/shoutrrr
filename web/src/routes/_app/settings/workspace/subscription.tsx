import { createFileRoute } from '@tanstack/react-router';

import WorkspaceSubscription from '@/pages/settings/workspace/subscription';

export const Route = createFileRoute('/_app/settings/workspace/subscription')({
    component: WorkspaceSubscription,
});

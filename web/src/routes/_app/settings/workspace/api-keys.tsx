import { createFileRoute } from '@tanstack/react-router';

import WorkspaceApiKeys from '@/pages/settings/workspace/api-keys';

export const Route = createFileRoute('/_app/settings/workspace/api-keys')({
    component: WorkspaceApiKeys,
});

import { createFileRoute } from '@tanstack/react-router';

import WorkspaceMembers from '@/pages/settings/workspace/members';

export const Route = createFileRoute('/_app/settings/workspace/members')({
    component: WorkspaceMembers,
});

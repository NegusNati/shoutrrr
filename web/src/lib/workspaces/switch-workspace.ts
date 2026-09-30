import { api } from '@/lib/api';
import { queryClient } from '@/lib/query-client';

type SwitchWorkspaceOptions = {
    onFinish?: () => void;
};

/**
 * POSTs the workspace switch to the API and refreshes the session bootstrap so
 * every consumer of /me (sidebar, workspace selector, dashboards) sees the new
 * current workspace. Mirrors the old router.post(..., preserveState: false).
 */
export async function switchWorkspace(
    workspaceId: string,
    options: SwitchWorkspaceOptions = {},
) {
    try {
        await api.post('workspaces/switch', { workspace_id: workspaceId });
        await queryClient.invalidateQueries();
    } finally {
        options.onFinish?.();
    }
}

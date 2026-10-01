import { queryOptions, useMutation } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { queryClient } from '@/lib/query-client';
import { meQuery } from '@/features/me/me';

export type InvitationView = {
    /** Model id — the accept endpoint is keyed on it, not the token. */
    id: string;
    workspace_name: string;
    role: string;
    inviter_name: string;
    expires_at: string;
    user_exists: boolean;
};

/** Public read — the token is the capability, no session needed. */
export const invitationQuery = (token: string) =>
    queryOptions({
        queryKey: ['invitation', token] as const,
        queryFn: (): Promise<InvitationView> =>
            apiFetch<InvitationView>(endpoints.invitation(token)),
        // A stale token shouldn't be retried forever; 404s are terminal.
        retry: false,
        staleTime: 30_000,
    });

/**
 * Signed-in accept — mirrors the legacy auto-accept: returns the API message
 * for the toast, refreshes the workspace list so the new workspace appears.
 */
export function useAcceptInvitation() {
    return useMutation({
        mutationFn: (invitationId: string) =>
            apiFetch<{ message: string }>(
                endpoints.workspaceInvitationAccept(invitationId),
                { method: 'POST' },
            ),
        onSuccess: () => queryClient.invalidateQueries({ queryKey: meQuery.queryKey }),
    });
}

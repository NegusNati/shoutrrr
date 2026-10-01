import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';

export type InvitationDetails = {
    invitation: {
        token: string;
        id: string;
        workspace_name: string;
        role: string;
        inviter_name: string;
        expires_at: string;
    };
    userExists: boolean;
    loginUrl: string;
    registerUrl: string;
};

export const publicInvitationQuery = (token: string) =>
    queryOptions({
        queryKey: ['workspace-invitation', token],
        queryFn: () =>
            apiFetch<InvitationDetails>(
                `workspace-invitations/token/${encodeURIComponent(token)}`,
            ),
        retry: false,
        staleTime: 60_000,
    });

export function acceptInvitation(invitationId: string) {
    return apiFetch<{ message: string }>(
        `workspace-invitations/${invitationId}/accept`,
        { method: 'POST' },
    );
}

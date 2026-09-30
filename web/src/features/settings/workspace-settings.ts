import {
    queryOptions,
    useMutation,
    useQueryClient,
} from '@tanstack/react-query';
import { useNavigate } from '@tanstack/react-router';
import { toast } from 'sonner';

import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { removeById, replaceById } from '@/lib/optimistic';
import { queryClient } from '@/lib/query-client';
import type { WorkspacesData } from '@/types/workspace';

export type WorkspaceOverviewData = {
    workspace: {
        id: string;
        name: string;
        slug: string;
        logo: string;
        owner_id: string;
    };
    canManage: boolean;
    isOwner: boolean;
    canDelete: boolean;
    deleteDisabledReason: string | null;
    timezone: string;
    timezones: string[];
};

export type Member = {
    id: string;
    user_id: string;
    name: string;
    email: string;
    avatar: string;
    role: string;
    is_owner: boolean;
    created_at: string;
};

export type Invitation = {
    id: string;
    email: string;
    role: string;
    invited_by: string | null;
    expires_at: string;
    created_at: string;
};

export type WorkspaceMembersData = {
    members: Member[];
    pendingInvitations: Invitation[];
    canManage: boolean;
    availableRoles: string[];
};

export type WorkspaceApiKey = {
    id: string;
    name: string;
    last_four: string | null;
    scope: 'read' | 'write';
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
};

export type WorkspaceApiKeysData = {
    apiKeys: WorkspaceApiKey[];
};

export type CreatedApiKey = {
    apiKey: WorkspaceApiKey;
    /** Shown exactly once — the API never returns it again. */
    plainTextApiKey: string;
};

export type WorkspaceSubscriptionData = {
    subscribed: boolean;
    monthlyPrice: number;
    monthlyXBudgetMicrousd: number | null;
    monthlyXBudgetUsedMicrousd: number;
    monthlyXBudgetRemainingMicrousd: number | null;
    canManageSubscription: boolean;
    canAccessPortal: boolean;
};

export const workspaceSettingsKeys = {
    overview: ['settings', 'workspace', 'overview'] as const,
    members: ['settings', 'workspace', 'members'] as const,
    apiKeys: ['settings', 'workspace', 'api-keys'] as const,
    subscription: ['settings', 'workspace', 'subscription'] as const,
};

export const workspaceOverviewQuery = queryOptions({
    queryKey: workspaceSettingsKeys.overview,
    queryFn: (): Promise<WorkspaceOverviewData> =>
        apiFetch<WorkspaceOverviewData>(endpoints.settingsWorkspace),
});

export const workspaceMembersQuery = queryOptions({
    queryKey: workspaceSettingsKeys.members,
    queryFn: (): Promise<WorkspaceMembersData> =>
        apiFetch<WorkspaceMembersData>(endpoints.settingsWorkspaceMembers),
});

export const workspaceApiKeysQuery = queryOptions({
    queryKey: workspaceSettingsKeys.apiKeys,
    queryFn: (): Promise<WorkspaceApiKeysData> =>
        apiFetch<WorkspaceApiKeysData>(endpoints.settingsWorkspaceApiKeys),
});

export const workspaceSubscriptionQuery = queryOptions({
    queryKey: workspaceSettingsKeys.subscription,
    queryFn: (): Promise<WorkspaceSubscriptionData> =>
        apiFetch<WorkspaceSubscriptionData>(
            endpoints.settingsWorkspaceSubscription,
        ),
});

/**
 * POST + `_method: 'PATCH'` — Laravel reads PATCH semantics off the spoofed
 * form so the workspace photo can ride along as multipart.
 */
export async function updateWorkspace(
    payload: FormData,
): Promise<WorkspaceOverviewData> {
    payload.set('_method', 'PATCH');

    return apiFetch<WorkspaceOverviewData>(endpoints.settingsWorkspace, {
        method: 'POST',
        body: payload,
    });
}

export async function updateWorkspaceTimezone(
    timezone: string,
): Promise<{ timezone: string }> {
    return apiFetch(endpoints.settingsWorkspaceTimezone, {
        method: 'PUT',
        body: { timezone },
    });
}

/**
 * Leave/delete/transfer change which workspace is current — callers invalidate
 * everything afterwards so the shell (sidebar, workspace selector) refreshes.
 */
export async function leaveWorkspace(): Promise<{
    workspaces: WorkspacesData;
}> {
    return apiFetch(endpoints.settingsWorkspaceLeave, { method: 'POST' });
}

export async function deleteWorkspace(): Promise<{
    workspaces: WorkspacesData;
}> {
    return apiFetch(endpoints.settingsWorkspace, { method: 'DELETE' });
}

export async function transferOwnership(
    membershipId: string,
): Promise<{ workspaces: WorkspacesData }> {
    return apiFetch(endpoints.settingsWorkspaceTransfer, {
        method: 'POST',
        body: { membership_id: membershipId },
    });
}

/** Optimistic PATCH of a member's role; the server remains source of truth on settle. */
export function useUpdateMemberRole() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: ({ memberId, role }: { memberId: string; role: string }) =>
            apiFetch(endpoints.settingsWorkspaceMember(memberId), {
                method: 'PATCH',
                body: { role },
            }),
        onMutate: async ({ memberId, role }) => {
            await client.cancelQueries({
                queryKey: workspaceSettingsKeys.members,
            });
            const previous = client.getQueryData<WorkspaceMembersData>(
                workspaceSettingsKeys.members,
            );
            client.setQueryData<WorkspaceMembersData>(
                workspaceSettingsKeys.members,
                (data) =>
                    data && {
                        ...data,
                        members: replaceById(data.members, memberId, (m) => ({
                            ...m,
                            role,
                        })),
                    },
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(
                    workspaceSettingsKeys.members,
                    context.previous,
                );
            }
            toast.error(getErrorMessage(error, 'Could not update the role.'));
        },
        onSuccess: () => toast.success('Member role updated'),
        onSettled: () =>
            client.invalidateQueries({
                queryKey: workspaceSettingsKeys.members,
            }),
    });
}

/** Optimistic remove of a member row; rollback on failure. */
export function useRemoveMember() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (memberId: string) =>
            apiFetch(endpoints.settingsWorkspaceMember(memberId), {
                method: 'DELETE',
            }),
        onMutate: async (memberId) => {
            await client.cancelQueries({
                queryKey: workspaceSettingsKeys.members,
            });
            const previous = client.getQueryData<WorkspaceMembersData>(
                workspaceSettingsKeys.members,
            );
            client.setQueryData<WorkspaceMembersData>(
                workspaceSettingsKeys.members,
                (data) =>
                    data && {
                        ...data,
                        members: removeById(data.members, memberId),
                    },
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(
                    workspaceSettingsKeys.members,
                    context.previous,
                );
            }
            toast.error(getErrorMessage(error, 'Could not remove the member.'));
        },
        onSuccess: () => toast.success('Member removed'),
        onSettled: () =>
            client.invalidateQueries({
                queryKey: workspaceSettingsKeys.members,
            }),
    });
}

/** Optimistic cancel of a pending invitation; rollback on failure. */
export function useCancelInvitation() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (invitationId: string) =>
            apiFetch(endpoints.settingsWorkspaceInvitation(invitationId), {
                method: 'DELETE',
            }),
        onMutate: async (invitationId) => {
            await client.cancelQueries({
                queryKey: workspaceSettingsKeys.members,
            });
            const previous = client.getQueryData<WorkspaceMembersData>(
                workspaceSettingsKeys.members,
            );
            client.setQueryData<WorkspaceMembersData>(
                workspaceSettingsKeys.members,
                (data) =>
                    data && {
                        ...data,
                        pendingInvitations: removeById(
                            data.pendingInvitations,
                            invitationId,
                        ),
                    },
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(
                    workspaceSettingsKeys.members,
                    context.previous,
                );
            }
            toast.error(
                getErrorMessage(error, 'Could not cancel the invitation.'),
            );
        },
        onSuccess: () => toast.success('Invitation cancelled'),
        onSettled: () =>
            client.invalidateQueries({
                queryKey: workspaceSettingsKeys.members,
            }),
    });
}

/** Revoke an API key and refresh the list. */
export function useRevokeApiKey() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (keyId: string) =>
            apiFetch(endpoints.settingsWorkspaceApiKey(keyId), {
                method: 'DELETE',
            }),
        onError: (error) =>
            toast.error(getErrorMessage(error, 'Could not revoke the key.')),
        onSuccess: () => toast.success('API key revoked'),
        onSettled: () =>
            client.invalidateQueries({
                queryKey: workspaceSettingsKeys.apiKeys,
            }),
    });
}

/**
 * Leave/delete/transfer change which workspace is current — invalidate
 * everything so the session bootstrap (sidebar, workspace selector) refreshes,
 * then send the member back to the dashboard since the settings page they were
 * on belonged to the previous workspace context.
 */
export function useLeaveWorkspaceContext() {
    const navigate = useNavigate();

    return async () => {
        await queryClient.invalidateQueries();
        await navigate({ to: '/dashboard' });
    };
}

import { queryOptions } from '@tanstack/react-query';

import { apiFetch, apiUpload } from '@/lib/api';

export type WorkspaceInfo = {
    id: string;
    name: string;
    slug: string;
    logo: string;
    owner_id: string;
};

export type WorkspaceOverviewData = {
    workspace: WorkspaceInfo;
    canManage: boolean;
    isOwner: boolean;
    canDelete: boolean;
    deleteDisabledReason: string | null;
    timezone: string;
    timezones: string[];
};

export const workspaceOverviewQuery = queryOptions({
    queryKey: ['workspace-settings', 'overview'],
    queryFn: () => apiFetch<WorkspaceOverviewData>('settings/workspace'),
});

/** PATCH with a `_method` spoof so the logo can ride in multipart FormData. */
export const updateWorkspace = (data: { name: string; photo: File | null }) => {
    const form = new FormData();
    form.set('_method', 'PATCH');
    form.set('name', data.name);
    if (data.photo) {
        form.set('photo', data.photo);
    }

    return apiUpload<{ message: string }>('settings/workspace', form);
};

export const updateTimezone = (timezone: string) =>
    apiFetch<{ message: string }>('settings/workspace/timezone', {
        method: 'PUT',
        body: { timezone },
    });

export const leaveWorkspace = (workspaceId: string) =>
    apiFetch<{ message: string }>(`workspaces/${workspaceId}/leave`, {
        method: 'DELETE',
    });

export const deleteWorkspace = (workspaceId: string) =>
    apiFetch<{ message: string }>(`workspaces/${workspaceId}`, {
        method: 'DELETE',
    });

export const transferOwnership = (workspaceId: string, membershipId: string) =>
    apiFetch<{ message: string }>(`workspaces/${workspaceId}/transfer`, {
        method: 'POST',
        body: { membership_id: membershipId },
    });

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

export type MembersData = {
    members: Member[];
    pendingInvitations: Invitation[];
    canManage: boolean;
    availableRoles: string[];
};

export const workspaceMembersQuery = queryOptions({
    queryKey: ['workspace-settings', 'members'],
    queryFn: () => apiFetch<MembersData>('settings/workspace/members'),
});

export const inviteMember = (body: { email: string; role: string }) =>
    apiFetch<{ message: string }>('settings/workspace/invite', {
        method: 'POST',
        body,
    });

export const updateMemberRole = (membershipId: string, role: string) =>
    apiFetch<{ message: string }>(
        `settings/workspace/members/${membershipId}`,
        {
            method: 'PATCH',
            body: { role },
        },
    );

export const removeMember = (membershipId: string) =>
    apiFetch<{ message: string }>(
        `settings/workspace/members/${membershipId}`,
        {
            method: 'DELETE',
        },
    );

export const cancelInvitation = (invitationId: string) =>
    apiFetch<{ message: string }>(
        `settings/workspace/invitations/${invitationId}`,
        { method: 'DELETE' },
    );

export type ApiKey = {
    id: string;
    name: string;
    last_four: string | null;
    scope: 'read' | 'write';
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
};

export type ApiKeysData = {
    apiKeys: ApiKey[];
};

export const workspaceApiKeysQuery = queryOptions({
    queryKey: ['workspace-settings', 'api-keys'],
    queryFn: () => apiFetch<ApiKeysData>('settings/workspace/api-keys'),
});

export const createApiKey = (body: {
    name: string;
    scope: 'read' | 'write';
    expires_at?: string;
}) =>
    apiFetch<{ apiKey: ApiKey; plainTextApiKey: string }>(
        'settings/workspace/api-keys',
        { method: 'POST', body },
    );

export const revokeApiKey = (apiKeyId: string) =>
    apiFetch<{ message: string }>(`settings/workspace/api-keys/${apiKeyId}`, {
        method: 'DELETE',
    });

export type SubscriptionData = {
    subscribed: boolean;
    monthlyPrice: number;
    monthlyXBudgetMicrousd: number | null;
    monthlyXBudgetUsedMicrousd: number;
    monthlyXBudgetRemainingMicrousd: number | null;
    canManageSubscription: boolean;
    canAccessPortal: boolean;
};

export const subscriptionQuery = queryOptions({
    queryKey: ['workspace-settings', 'subscription'],
    queryFn: () =>
        apiFetch<SubscriptionData>('settings/workspace/subscription'),
});

export const billingCheckout = () =>
    apiFetch<{ url: string }>('billing/checkout', { method: 'POST' });

export const billingPortal = () =>
    apiFetch<{ url: string }>('billing/portal', { method: 'POST' });

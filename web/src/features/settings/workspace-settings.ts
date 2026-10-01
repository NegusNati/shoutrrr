import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';

export type WorkspaceSettingsWorkspace = {
    id: string;
    name: string;
    slug: string;
    logo: string;
    owner_id: string;
};

export type WorkspaceSettingsData = {
    workspace: WorkspaceSettingsWorkspace;
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

export type ApiKey = {
    id: string;
    name: string;
    last_four: string;
    scope: string;
    last_used_at: string | null;
    expires_at: string | null;
    created_at: string;
};

export type ApiKeysData = {
    apiKeys: ApiKey[];
};

export type CreateApiKeyResponse = {
    apiKey: ApiKey;
    plainTextApiKey: string;
};

export type SubscriptionData = {
    subscribed: boolean;
    monthlyPrice: number;
    monthlyXBudgetMicrousd: number;
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

export const workspaceSettingsQuery = queryOptions({
    queryKey: workspaceSettingsKeys.overview,
    queryFn: () => apiFetch<WorkspaceSettingsData>(endpoints.settingsWorkspace),
});

export const workspaceMembersQuery = queryOptions({
    queryKey: workspaceSettingsKeys.members,
    queryFn: () =>
        apiFetch<WorkspaceMembersData>(endpoints.settingsWorkspaceMembers),
});

export const workspaceApiKeysQuery = queryOptions({
    queryKey: workspaceSettingsKeys.apiKeys,
    queryFn: () => apiFetch<ApiKeysData>(endpoints.settingsWorkspaceApiKeys),
});

export const workspaceSubscriptionQuery = queryOptions({
    queryKey: workspaceSettingsKeys.subscription,
    queryFn: () =>
        apiFetch<SubscriptionData>(endpoints.settingsWorkspaceSubscription),
});

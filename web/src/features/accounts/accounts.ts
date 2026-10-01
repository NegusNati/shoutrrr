import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';

export type Account = {
    id: string;
    platform: string;
    platform_label: string;
    handle: string;
    display_name: string | null;
    avatar_url: string | null;
    status: 'active' | 'needs_attention';
    status_label: string;
    auth_method: string;
    connected_by: string | null;
    token_expires_at: string | null;
    max_text_length: number;
    max_video_duration_seconds: number;
    x_premium: boolean;
    x_subscription_tier: 'free' | 'basic' | 'premium' | 'premium_plus' | null;
    x_subscription_label: string | null;
    x_subscription_checked_at: string | null;
    is_linkedin_page: boolean;
    is_default: boolean;
    disabled: boolean;
    pds_url: string | null;
    auto_repost_enabled: boolean;
};

export type Capability = {
    platform: string;
    label: string;
    supportsOAuth: boolean;
    supportsAppPassword: boolean;
    supportsWebhook: boolean;
    configured: boolean;
    launched: boolean;
    enabled: boolean;
};

export type AccountsData = {
    accounts: Account[];
    capabilities: Capability[];
    can_manage: boolean;
};

export const accountsQuery = queryOptions({
    queryKey: ['accounts'],
    queryFn: () => apiFetch<AccountsData>('connected-accounts'),
});

export const toggleAccount = (accountId: string) =>
    apiFetch<{ disabled: boolean }>(`connected-accounts/${accountId}/toggle`, {
        method: 'PATCH',
        body: {},
    });

export const makeDefaultAccount = (accountId: string) =>
    apiFetch<{ is_default: boolean }>(
        `connected-accounts/${accountId}/default`,
        { method: 'POST' },
    );

export const setAutoRepost = (accountId: string, enabled: boolean) =>
    apiFetch<{ auto_repost_enabled: boolean }>(
        `connected-accounts/${accountId}/auto-repost`,
        { method: 'PATCH', body: { enabled } },
    );

export const refreshXAccountTier = (accountId: string) =>
    apiFetch<{
        x_subscription_label: string | null;
        max_text_length: number;
        max_video_duration_seconds: number;
    }>(`connected-accounts/${accountId}/refresh-x-tier`, { method: 'POST' });

export type ReconnectPayload =
    | { webhook_url: string }
    | { identifier: string; app_password: string; pds_url?: string };

export const reconnectAccount = (
    accountId: string,
    payload: ReconnectPayload,
) =>
    apiFetch<{ reconnected: boolean }>(
        `connected-accounts/${accountId}/reconnect`,
        { method: 'POST', body: payload },
    );

export const disconnectAccount = (accountId: string) =>
    apiFetch<{ deleted: boolean }>(`connected-accounts/${accountId}`, {
        method: 'DELETE',
    });

export const connectBluesky = (payload: {
    identifier: string;
    app_password: string;
    pds_url?: string;
    dm_access?: boolean;
}) =>
    apiFetch<{ connected: boolean }>('connected-accounts/connect/bluesky', {
        method: 'POST',
        body: payload,
    });

export const connectDiscord = (webhook_url: string) =>
    apiFetch<{ connected: boolean }>('connected-accounts/connect/discord', {
        method: 'POST',
        body: { webhook_url },
    });

export type MetaAsset = {
    key: string;
    pageId: string;
    pageName: string;
    igUserId: string | null;
    igUsername: string | null;
    igAvatarUrl: string | null;
    platforms: string[];
};

export type MetaSelection = { assetKey: string; platform: string };

export type SelectionState = Record<string, Record<string, boolean>>;

/** Flattens per-asset/per-platform checkbox state into `{assetKey, platform}[]`. */
export function buildMetaSelection(selected: SelectionState): MetaSelection[] {
    return Object.entries(selected).flatMap(([assetKey, platforms]) =>
        Object.entries(platforms)
            .filter(([, checked]) => checked)
            .map(([platform]) => ({ assetKey, platform })),
    );
}

export const metaConnectQuery = queryOptions({
    queryKey: ['accounts', 'connect-meta'],
    queryFn: () =>
        apiFetch<{ assets: MetaAsset[] }>('connected-accounts/connect/meta'),
});

export const submitMetaSelection = (selected: MetaSelection[]) =>
    apiFetch<{ connected: number }>('connected-accounts/connect/meta', {
        method: 'POST',
        body: { selected },
    });

export type LinkedInPerson = {
    remoteAccountId: string;
    handle: string;
    displayName: string | null;
    avatarUrl: string | null;
};

export type LinkedInOrganization = {
    id: string;
    urn: string;
    name: string;
    vanityName: string;
};

export type LinkedInSelection =
    | { type: 'person' }
    | { type: 'organization'; id: string };

export const linkedInConnectQuery = queryOptions({
    queryKey: ['accounts', 'connect-linkedin'],
    queryFn: () =>
        apiFetch<{
            person: LinkedInPerson;
            organizations: LinkedInOrganization[];
        }>('connected-accounts/connect/linkedin'),
});

export const submitLinkedInSelection = (selected: LinkedInSelection[]) =>
    apiFetch<{ connected: number }>('connected-accounts/connect/linkedin', {
        method: 'POST',
        body: { selected },
    });

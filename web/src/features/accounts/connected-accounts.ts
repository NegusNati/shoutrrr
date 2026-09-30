import {
    queryOptions,
    useMutation,
    useQueryClient,
} from '@tanstack/react-query';
import { toast } from 'sonner';

import { apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { removeById, replaceById } from '@/lib/optimistic';

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

export type ConnectedAccountsData = {
    accounts: Account[];
    capabilities: Capability[];
    canManage: boolean;
};

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

export type LinkedInPickerData = {
    person: LinkedInPerson;
    organizations: LinkedInOrganization[];
};

export type LinkedInSelection =
    | { type: 'person' }
    | { type: 'organization'; id: string };

export const accountsKeys = {
    manage: ['accounts', 'manage'] as const,
    metaPicker: ['accounts', 'connect', 'meta'] as const,
    linkedinPicker: ['accounts', 'connect', 'linkedin'] as const,
};

export const connectedAccountsQuery = queryOptions({
    queryKey: accountsKeys.manage,
    queryFn: (): Promise<ConnectedAccountsData> =>
        apiFetch<ConnectedAccountsData>(endpoints.connectedAccountsManage),
});

export const metaPickerQuery = queryOptions({
    queryKey: accountsKeys.metaPicker,
    queryFn: (): Promise<{ assets: MetaAsset[] }> =>
        apiFetch<{ assets: MetaAsset[] }>(endpoints.connectMeta),
    retry: false,
    staleTime: 30_000,
});

export const linkedinPickerQuery = queryOptions({
    queryKey: accountsKeys.linkedinPicker,
    queryFn: (): Promise<LinkedInPickerData> =>
        apiFetch<LinkedInPickerData>(endpoints.connectLinkedin),
    retry: false,
    staleTime: 30_000,
});

export type BlueskyConnectPayload = {
    identifier: string;
    app_password: string;
    pds_url?: string;
    dm_access?: boolean;
};

export async function connectBluesky(
    payload: BlueskyConnectPayload,
): Promise<{ connected: true }> {
    return apiFetch(endpoints.connectBluesky, {
        method: 'POST',
        body: payload,
    });
}

export async function connectDiscord(payload: {
    webhook_url: string;
}): Promise<{ connected: true }> {
    return apiFetch(endpoints.connectDiscord, {
        method: 'POST',
        body: payload,
    });
}

export async function storeMetaSelection(
    selected: MetaSelection[],
): Promise<{ connected: number }> {
    return apiFetch(endpoints.connectMeta, {
        method: 'POST',
        body: { selected },
    });
}

export async function storeLinkedinSelection(
    selected: LinkedInSelection[],
): Promise<{ connected: number }> {
    return apiFetch(endpoints.connectLinkedin, {
        method: 'POST',
        body: { selected },
    });
}

/**
 * App-password (Bluesky) and webhook (Discord) reconnects resubmit credentials;
 * OAuth accounts get back `{ url }` for the browser to follow into the
 * provider flow.
 */
export type ReconnectPayload =
    | { identifier: string; app_password: string; pds_url?: string }
    | { webhook_url: string }
    | Record<string, never>;

export type ReconnectResponse = { url: string } | { reconnected: true };

export async function reconnectAccount(
    accountId: string,
    payload: ReconnectPayload,
): Promise<ReconnectResponse> {
    return apiFetch<ReconnectResponse>(
        endpoints.connectedAccountReconnect(accountId),
        { method: 'POST', body: payload },
    );
}

function patchAccounts(
    data: ConnectedAccountsData | undefined,
    accountId: string,
    patch: (account: Account) => Account,
): ConnectedAccountsData | undefined {
    return (
        data && {
            ...data,
            accounts: replaceById(data.accounts, accountId, patch),
        }
    );
}

/** Flips `disabled`; disabling also drops the workspace-default flag. */
export function useToggleAccount() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: ({ accountId }: { accountId: string; enabled: boolean }) =>
            apiFetch(endpoints.connectedAccountToggle(accountId), {
                method: 'PATCH',
            }),
        onMutate: async ({ accountId, enabled }) => {
            await client.cancelQueries({ queryKey: accountsKeys.manage });
            const previous = client.getQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
            );
            client.setQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
                (data) =>
                    patchAccounts(data, accountId, (account) => ({
                        ...account,
                        disabled: !enabled,
                        is_default: enabled ? account.is_default : false,
                    })),
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(accountsKeys.manage, context.previous);
            }
            toast.error(
                getErrorMessage(error, 'Could not update the account.'),
            );
        },
        onSettled: () =>
            client.invalidateQueries({ queryKey: accountsKeys.manage }),
    });
}

export function useMakeDefault() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (accountId: string) =>
            apiFetch(endpoints.connectedAccountDefault(accountId), {
                method: 'POST',
            }),
        onMutate: async (accountId) => {
            await client.cancelQueries({ queryKey: accountsKeys.manage });
            const previous = client.getQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
            );
            client.setQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
                (data) =>
                    data && {
                        ...data,
                        accounts: data.accounts.map((account) => ({
                            ...account,
                            is_default: account.id === accountId,
                        })),
                    },
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(accountsKeys.manage, context.previous);
            }
            toast.error(
                getErrorMessage(error, 'Could not set the default account.'),
            );
        },
        onSuccess: () => toast.success('Default account updated'),
        onSettled: () =>
            client.invalidateQueries({ queryKey: accountsKeys.manage }),
    });
}

export function useAutoRepost() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: ({
            accountId,
            enabled,
        }: {
            accountId: string;
            enabled: boolean;
        }) =>
            apiFetch(endpoints.connectedAccountAutoRepost(accountId), {
                method: 'PATCH',
                body: { enabled },
            }),
        onMutate: async ({ accountId, enabled }) => {
            await client.cancelQueries({ queryKey: accountsKeys.manage });
            const previous = client.getQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
            );
            client.setQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
                (data) =>
                    patchAccounts(data, accountId, (account) => ({
                        ...account,
                        auto_repost_enabled: enabled,
                    })),
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(accountsKeys.manage, context.previous);
            }
            toast.error(getErrorMessage(error, 'Could not update auto-boost.'));
        },
        onSettled: () =>
            client.invalidateQueries({ queryKey: accountsKeys.manage }),
    });
}

export function useRefreshXTier() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (accountId: string) =>
            apiFetch(endpoints.connectedAccountRefreshXTier(accountId), {
                method: 'POST',
            }),
        onSuccess: () => toast.success('X account tier refreshed'),
        onError: (error) =>
            toast.error(
                getErrorMessage(error, 'Could not refresh the X tier.'),
            ),
        onSettled: () =>
            client.invalidateQueries({ queryKey: accountsKeys.manage }),
    });
}

export function useDeleteAccount() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (accountId: string) =>
            apiFetch(endpoints.connectedAccount(accountId), {
                method: 'DELETE',
            }),
        onMutate: async (accountId) => {
            await client.cancelQueries({ queryKey: accountsKeys.manage });
            const previous = client.getQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
            );
            client.setQueryData<ConnectedAccountsData>(
                accountsKeys.manage,
                (data) =>
                    data && {
                        ...data,
                        accounts: removeById(data.accounts, accountId),
                    },
            );
            return { previous };
        },
        onError: (error, _vars, context) => {
            if (context?.previous) {
                client.setQueryData(accountsKeys.manage, context.previous);
            }
            toast.error(
                getErrorMessage(error, 'Could not disconnect the account.'),
            );
        },
        onSuccess: () => toast.success('Account disconnected'),
        onSettled: () =>
            client.invalidateQueries({ queryKey: accountsKeys.manage }),
    });
}

/** OAuth reconnect: the API hands back the provider redirect URL to follow. */
export function useReconnectOAuth() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (accountId: string) => reconnectAccount(accountId, {}),
        onSuccess: (result) => {
            if ('url' in result) {
                window.location.href = result.url;
            }
        },
        onError: (error) =>
            toast.error(
                getErrorMessage(error, 'Could not start the reconnect.'),
            ),
        onSettled: () =>
            client.invalidateQueries({ queryKey: accountsKeys.manage }),
    });
}

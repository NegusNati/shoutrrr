import {
    queryOptions,
    useMutation,
    useQueryClient,
} from '@tanstack/react-query';
import { toast } from 'sonner';

import { ApiError, apiFetch, getErrorMessage } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import { removeById } from '@/lib/optimistic';
import type { PlatformName } from '@/types/compose';

export type InstanceGeneralSettings = {
    registrations_enabled: boolean;
    workspace_creation_enabled: boolean;
    usage_tracking_enabled: boolean;
    quote_tweets_enabled: boolean;
};

export type InstanceSettingsData = {
    settings: InstanceGeneralSettings;
    workspaces_enabled: boolean;
};

export type PollingSectionKey =
    | 'engagement'
    | 'post_metrics'
    | 'account_metrics';

export type PollingGroup = Record<PlatformName, number> & {
    enabled: Record<PlatformName, boolean>;
};

export type PollingSettings = {
    engagement: PollingGroup;
    post_metrics: PollingGroup;
    account_metrics: PollingGroup;
    /** Instance-wide master switches. When off, the matching sections are moot. */
    metrics_enabled: boolean;
    engagement_enabled: boolean;
    messages_enabled: boolean;
    /** Opt-in to requesting DM OAuth scopes at connect/re-auth. Requires messages_enabled. */
    direct_messages_enabled: boolean;
};

export type SectionPlatform = { platform: PlatformName; label: string };

export type InstancePollingData = {
    settings: PollingSettings;
    sections: Record<PollingSectionKey, SectionPlatform[]>;
};

export type PlatformToggle = {
    platform: PlatformName;
    label: string;
    enabled: boolean;
    configured: boolean;
};

export type InstancePlatformsData = {
    platforms: PlatformToggle[];
    linkedin_community_management_enabled: boolean;
};

export type WorkspaceQuota = {
    kind: 'default' | 'custom' | 'unlimited';
    dollars: number | null;
};

export type WorkspaceUsageRow = {
    id: string;
    name: string;
    x_estimated_cost_usd: number;
    x_previous_cost_usd: number;
    x_cost_delta_usd: number;
    quota: WorkspaceQuota;
    percent_used: number | null;
};

export type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

export type WorkspaceUsagePaginator = {
    data: WorkspaceUsageRow[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
};

export type UsageFilters = {
    search: string | null;
    sort: 'spend' | 'name';
    workspace: string | null;
    page: number;
};

export type InstanceUsageData = {
    filters: { search: string | null; sort: 'spend' | 'name' };
    instance_summary: {
        workspace_count: number;
        x_estimated_cost_usd: number;
    };
    workspace_usage: WorkspaceUsagePaginator;
    pricing_source: string;
    pricing_currency: string;
    x_usage_available: boolean;
};

export type PricingEstimate = {
    resource: string;
    label: string;
    unit_cost_usd: number;
    estimated_cost_usd: number;
};

export type DrilldownCounter = {
    id: string;
    period_start: string;
    period_end: string;
    category: string;
    platform: string;
    operation: string;
    event_count: number;
    total_quota: number;
    pricing: PricingEstimate | null;
};

export type DrilldownErrorEvent = {
    id: string;
    category: string;
    operation: string;
    platform: string;
    quota_weight: number;
    meta: Record<string, unknown> | null;
    occurred_at: string;
};

export type DrilldownOwner = {
    name: string;
    email: string;
    avatar: string;
};

export type InstanceUsageDrilldown = {
    workspace: {
        id: string;
        name: string;
        is_initial: boolean;
        quota: WorkspaceQuota;
        owner: DrilldownOwner | null;
    };
    counters: DrilldownCounter[];
    error_events: DrilldownErrorEvent[];
};

export type XUsageApp = {
    app_id?: string;
    tweets_consumed?: number;
};

export type XUsageDay = {
    date?: string;
    usage?: XUsageApp[];
};

export type XUsageData = {
    cap_reset_day?: number;
    daily_client_app_usage?: XUsageDay[];
    daily_project_usage?: XUsageDay[] | { usage?: XUsageApp[] };
    project_cap?: number;
    project_id?: string;
    project_usage?: number;
};

export type XUsageResponse = {
    data: XUsageData | null;
    fetched_at: string;
    source: string;
};

export type InstanceUser = {
    id: string;
    name: string;
    email: string;
    avatar: string;
    created_at?: string;
};

export type InstanceAdminsData = {
    owners: InstanceUser[];
    users: InstanceUser[];
    search: string;
};

export const instanceSettingsKeys = {
    general: ['settings', 'instance', 'general'] as const,
    polling: ['settings', 'instance', 'polling'] as const,
    platforms: ['settings', 'instance', 'platforms'] as const,
    usage: (filters: UsageFilters) =>
        ['settings', 'instance', 'usage', filters] as const,
    drilldown: (workspaceId: string) =>
        ['settings', 'instance', 'usage', 'workspaces', workspaceId] as const,
    admins: (search: string) =>
        ['settings', 'instance', 'admins', search] as const,
};

export const instanceSettingsQuery = queryOptions({
    queryKey: instanceSettingsKeys.general,
    queryFn: (): Promise<InstanceSettingsData> =>
        apiFetch<InstanceSettingsData>(endpoints.settingsInstance),
});

export const instancePollingQuery = queryOptions({
    queryKey: instanceSettingsKeys.polling,
    queryFn: (): Promise<InstancePollingData> =>
        apiFetch<InstancePollingData>(endpoints.settingsInstancePolling),
});

export const instancePlatformsQuery = queryOptions({
    queryKey: instanceSettingsKeys.platforms,
    queryFn: (): Promise<InstancePlatformsData> =>
        apiFetch<InstancePlatformsData>(endpoints.settingsInstancePlatforms),
});

function usageQueryString(filters: UsageFilters): string {
    const params = new URLSearchParams();

    if (filters.search) {
        params.set('search', filters.search);
    }
    if (filters.sort !== 'spend') {
        params.set('sort', filters.sort);
    }
    if (filters.page > 1) {
        params.set('page', String(filters.page));
    }

    const query = params.toString();

    return query === '' ? '' : `?${query}`;
}

export const instanceUsageQuery = (filters: UsageFilters) =>
    queryOptions({
        queryKey: instanceSettingsKeys.usage(filters),
        queryFn: (): Promise<InstanceUsageData> =>
            apiFetch<InstanceUsageData>(
                `${endpoints.settingsInstanceUsage}${usageQueryString(filters)}`,
            ),
    });

export const instanceUsageDrilldownQuery = (workspaceId: string) =>
    queryOptions({
        queryKey: instanceSettingsKeys.drilldown(workspaceId),
        queryFn: (): Promise<InstanceUsageDrilldown> =>
            apiFetch<InstanceUsageDrilldown>(
                endpoints.settingsInstanceWorkspaceUsage(workspaceId),
            ),
    });

export const instanceAdminsQuery = (search: string) =>
    queryOptions({
        queryKey: instanceSettingsKeys.admins(search),
        queryFn: (): Promise<InstanceAdminsData> =>
            apiFetch<InstanceAdminsData>(
                search === ''
                    ? endpoints.settingsInstanceAdmins
                    : `${endpoints.settingsInstanceAdmins}?search=${encodeURIComponent(search)}`,
            ),
    });

export async function updateInstanceSettings(
    payload: InstanceGeneralSettings,
): Promise<InstanceSettingsData> {
    return apiFetch(endpoints.settingsInstance, {
        method: 'PUT',
        body: payload,
    });
}

export async function updateInstancePolling(
    payload: PollingSettings,
): Promise<InstancePollingData> {
    return apiFetch(endpoints.settingsInstancePolling, {
        method: 'PUT',
        body: payload,
    });
}

export async function updateInstancePlatforms(payload: {
    platforms: Record<string, boolean>;
    linkedin_community_management_enabled: boolean;
}): Promise<InstancePlatformsData> {
    return apiFetch(endpoints.settingsInstancePlatforms, {
        method: 'PUT',
        body: payload,
    });
}

export async function updateWorkspaceXBudget(
    workspaceId: string,
    payload: { unlimited: boolean; dollars: number | null },
): Promise<{ quota: WorkspaceQuota }> {
    return apiFetch(endpoints.settingsInstanceWorkspaceBudget(workspaceId), {
        method: 'PUT',
        body: payload,
    });
}

/** On-demand (click) fetch of the upstream X usage meter — not a query: the response belongs to local state, mirroring the legacy useHttp flow. */
export async function fetchXUsage(days = 7): Promise<XUsageResponse> {
    return apiFetch(`${endpoints.settingsInstanceXUsage}?days=${days}`);
}

/** Optimistic add — the owner lands in the owners table immediately; rollback + toast on failure. */
export function useAddInstanceOwner() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (email: string) =>
            apiFetch<{ owner: InstanceUser }>(endpoints.settingsInstanceAdmins, {
                method: 'POST',
                body: { email },
            }),
        onError: (error) => {
            toast.error(
                error instanceof ApiError
                    ? (error.fieldError('email') ??
                          getErrorMessage(error, 'Could not add the owner.'))
                    : getErrorMessage(error, 'Could not add the owner.'),
            );
        },
        onSuccess: () => toast.success('Instance owner added'),
        onSettled: () =>
            client.invalidateQueries({
                queryKey: ['settings', 'instance', 'admins'],
            }),
    });
}

/** Optimistic remove of an owner row; rollback on failure. */
export function useRemoveInstanceOwner() {
    const client = useQueryClient();

    return useMutation({
        mutationFn: (ownerId: string) =>
            apiFetch(endpoints.settingsInstanceAdmin(ownerId), {
                method: 'DELETE',
            }),
        onMutate: async (ownerId) => {
            await client.cancelQueries({
                queryKey: ['settings', 'instance', 'admins'],
            });
            const snapshots = client.getQueriesData<InstanceAdminsData>({
                queryKey: ['settings', 'instance', 'admins'],
            });
            for (const [key, data] of snapshots) {
                client.setQueryData<InstanceAdminsData>(
                    key,
                    data && {
                        ...data,
                        owners: removeById(data.owners, ownerId),
                    },
                );
            }
            return { snapshots };
        },
        onError: (error, _vars, context) => {
            for (const [key, data] of context?.snapshots ?? []) {
                client.setQueryData(key, data);
            }
            toast.error(
                error instanceof ApiError
                    ? (error.fieldError('owner') ??
                          getErrorMessage(error, 'Could not remove the owner.'))
                    : getErrorMessage(error, 'Could not remove the owner.'),
            );
        },
        onSuccess: () => toast.success('Instance owner removed'),
        onSettled: () =>
            client.invalidateQueries({
                queryKey: ['settings', 'instance', 'admins'],
            }),
    });
}

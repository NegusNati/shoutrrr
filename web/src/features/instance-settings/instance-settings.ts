import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import type { PlatformName } from '@/types/compose';

export type InstanceSettings = {
    registrations_enabled: boolean;
    workspace_creation_enabled: boolean;
    usage_tracking_enabled: boolean;
    quote_tweets_enabled: boolean;
    [key: string]: unknown;
};

export type InstanceSettingsData = {
    settings: InstanceSettings;
    workspaces_enabled: boolean;
};

export const instanceSettingsQuery = queryOptions({
    queryKey: ['instance-settings', 'general'],
    queryFn: () => apiFetch<InstanceSettingsData>('instance-settings'),
});

export const updateInstanceSettings = (settings: InstanceSettings) =>
    apiFetch<InstanceSettingsData>('instance-settings', {
        method: 'PUT',
        body: settings,
    });

export type PollingGroup = Record<PlatformName, number> & {
    enabled: Record<PlatformName, boolean>;
};

export type PollingSettings = {
    engagement: PollingGroup;
    post_metrics: PollingGroup;
    account_metrics: PollingGroup;
    metrics_enabled: boolean;
    engagement_enabled: boolean;
    messages_enabled: boolean;
    direct_messages_enabled: boolean;
};

export type PollingSectionKey =
    | 'engagement'
    | 'post_metrics'
    | 'account_metrics';

export type SectionPlatform = { platform: PlatformName; label: string };

export type PollingData = {
    settings: PollingSettings;
    sections: Record<PollingSectionKey, SectionPlatform[]>;
};

export const instancePollingQuery = queryOptions({
    queryKey: ['instance-settings', 'polling'],
    queryFn: () => apiFetch<PollingData>('instance-settings/polling'),
});

export const updatePollingSettings = (settings: PollingSettings) =>
    apiFetch<PollingData>('instance-settings/polling', {
        method: 'PUT',
        body: settings,
    });

export type PlatformToggle = {
    platform: PlatformName;
    label: string;
    enabled: boolean;
    configured: boolean;
};

export type PlatformsData = {
    platforms: PlatformToggle[];
    linkedin_community_management_enabled: boolean;
};

export const instancePlatformsQuery = queryOptions({
    queryKey: ['instance-settings', 'platforms'],
    queryFn: () => apiFetch<PlatformsData>('instance-settings/platforms'),
});

export const updatePlatformSettings = (data: {
    platforms: Record<string, boolean>;
    linkedin_community_management_enabled: boolean;
}) =>
    apiFetch<PlatformsData>('instance-settings/platforms', {
        method: 'PUT',
        body: data,
    });

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

export type Paginator<T> = {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
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

export type Drilldown = {
    workspace: {
        id: string;
        name: string;
        is_initial: boolean;
        quota: WorkspaceQuota;
        owner: DrilldownOwner | null;
    };
    counters: DrilldownCounter[];
    error_events: DrilldownErrorEvent[];
} | null;

export type UsageFilters = {
    search: string | null;
    sort: 'spend' | 'name';
    workspace: string | null;
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

export type UsageData = {
    filters: UsageFilters;
    instance_summary: {
        workspace_count: number;
        x_estimated_cost_usd: number;
    };
    workspace_usage: Paginator<WorkspaceUsageRow>;
    pricing_source: string;
    pricing_currency: string;
    x_usage_available: boolean;
    drilldown?: Drilldown;
};

export function usageQueryString(filters: UsageFilters, page?: number) {
    const params = new URLSearchParams();
    if (filters.search) {
        params.set('search', filters.search);
    }
    if (filters.sort !== 'spend') {
        params.set('sort', filters.sort);
    }
    if (filters.workspace) {
        params.set('workspace', filters.workspace);
    }
    if (page && page > 1) {
        params.set('page', String(page));
    }

    const qs = params.toString();

    return qs === '' ? '' : `?${qs}`;
}

export const instanceUsageQuery = (filters: UsageFilters, page?: number) =>
    queryOptions({
        queryKey: ['instance-settings', 'usage', filters, page ?? 1],
        queryFn: () =>
            apiFetch<UsageData>(
                `instance-settings/usage${usageQueryString(filters, page)}`,
            ),
    });

export const fetchXUsage = (days = 7) =>
    apiFetch<XUsageResponse>(`instance-settings/usage/x?days=${days}`);

export const updateWorkspaceBudget = (
    workspaceId: string,
    budget: { unlimited: boolean; dollars: number | '' | null },
) =>
    apiFetch<{ message: string }>(
        `instance-settings/usage/workspaces/${workspaceId}/budget`,
        {
            method: 'PUT',
            body: {
                unlimited: budget.unlimited,
                dollars:
                    budget.dollars === '' || budget.dollars === null
                        ? null
                        : budget.dollars,
            },
        },
    );

export type InstanceUser = {
    id: string;
    name: string;
    email: string;
    avatar: string;
    created_at?: string;
};

export type AdminsData = {
    owners: InstanceUser[];
    users: InstanceUser[];
    search: string;
};

export const instanceAdminsQuery = (search: string) =>
    queryOptions({
        queryKey: ['instance-settings', 'admins', search],
        queryFn: () =>
            apiFetch<AdminsData>(
                `instance-settings/admins${search ? `?search=${encodeURIComponent(search)}` : ''}`,
            ),
    });

export const addInstanceAdmin = (email: string) =>
    apiFetch<{ owner: InstanceUser }>('instance-settings/admins', {
        method: 'POST',
        body: { email },
    });

export const removeInstanceAdmin = (ownerId: string) =>
    apiFetch<{ message: string }>(`instance-settings/admins/${ownerId}`, {
        method: 'DELETE',
    });

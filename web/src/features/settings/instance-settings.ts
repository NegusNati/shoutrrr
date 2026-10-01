import { queryOptions } from '@tanstack/react-query';

import { apiFetch } from '@/lib/api';
import { endpoints } from '@/lib/api/endpoints';
import type { PlatformName } from '@/types/compose';

// ---- general ----------------------------------------------------------

export type InstanceGeneralSettings = {
    registrations_enabled: boolean;
    workspace_creation_enabled: boolean;
    usage_tracking_enabled: boolean;
    quote_tweets_enabled: boolean;
};

export type InstanceOverviewData = {
    settings: InstanceGeneralSettings;
    workspaces_enabled: boolean;
};

// ---- polling ----------------------------------------------------------

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

export type InstancePollingData = {
    settings: PollingSettings;
    sections: Record<PollingSectionKey, SectionPlatform[]>;
};

// ---- platforms --------------------------------------------------------

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

// ---- usage ------------------------------------------------------------

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
    page: number;
};

export type InstanceUsageData = {
    filters: {
        search: string | null;
        sort: 'spend' | 'name';
        workspace: string | null;
    };
    instance_summary: {
        workspace_count: number;
        x_estimated_cost_usd: number;
    };
    workspace_usage: WorkspaceUsagePaginator;
    pricing_source: string;
    pricing_currency: string;
    x_usage_available: boolean;
    drilldown: Drilldown;
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

// ---- admins -----------------------------------------------------------

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

// ---- queries ----------------------------------------------------------

export const instanceSettingsKeys = {
    overview: ['settings', 'instance', 'overview'] as const,
    polling: ['settings', 'instance', 'polling'] as const,
    platforms: ['settings', 'instance', 'platforms'] as const,
    usage: ['settings', 'instance', 'usage'] as const,
    admins: ['settings', 'instance', 'admins'] as const,
};

export const instanceOverviewQuery = queryOptions({
    queryKey: instanceSettingsKeys.overview,
    queryFn: () => apiFetch<InstanceOverviewData>(endpoints.settingsInstance),
});

export const instancePollingQuery = queryOptions({
    queryKey: instanceSettingsKeys.polling,
    queryFn: () =>
        apiFetch<InstancePollingData>(endpoints.settingsInstancePolling),
});

export const instancePlatformsQuery = queryOptions({
    queryKey: instanceSettingsKeys.platforms,
    queryFn: () =>
        apiFetch<InstancePlatformsData>(endpoints.settingsInstancePlatforms),
});

export const instanceUsageQuery = (filters: UsageFilters) =>
    queryOptions({
        queryKey: [...instanceSettingsKeys.usage, filters],
        queryFn: () => {
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
            if (filters.page > 1) {
                params.set('page', String(filters.page));
            }
            const qs = params.toString();
            return apiFetch<InstanceUsageData>(
                endpoints.settingsInstanceUsage + (qs ? `?${qs}` : ''),
            );
        },
        placeholderData: (previousData) => previousData,
    });

export const instanceAdminsQuery = (search: string) =>
    queryOptions({
        queryKey: [...instanceSettingsKeys.admins, search],
        queryFn: () =>
            apiFetch<InstanceAdminsData>(
                endpoints.settingsInstanceAdmins +
                    (search ? `?search=${encodeURIComponent(search)}` : ''),
            ),
    });

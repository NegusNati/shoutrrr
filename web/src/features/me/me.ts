import { queryOptions, useQuery, useQueryClient } from '@tanstack/react-query';

import { apiCall, apiClient } from '@/lib/api';
import type { Auth, SocialProviderOption } from '@/types/auth';
import type { Account, AccountSet, PlatformLimits } from '@/types/compose';
import type { NotificationsData } from '@/types/notifications';
import type { WorkspacesData } from '@/types/workspace';

export type ShellData = {
    accounts: Account[];
    sets: AccountSet[];
    limits: PlatformLimits[];
    unreadReplies: number;
    unreadMessages: number;
    gifs_enabled: boolean;
};

export type BillingData = {
    subscribed: boolean;
    manageUrl: string;
} | null;

export type CommunityData = {
    repoUrl: string;
    sponsorUrl: string;
    stars: number | null;
} | null;

/** Response of GET /api/v1/me — the session bootstrap for the SPA shell. */
export type MeData = {
    name: string;
    auth: Auth;
    workspaces: WorkspacesData;
    shell: ShellData;
    notifications: NotificationsData;
    socialite: { providers: SocialProviderOption[] };
    features: {
        analytics: boolean;
        billing: boolean;
        engagement: boolean;
        feedback: boolean;
        messages: boolean;
    };
    instance: { isOwner: boolean };
    billing: BillingData;
    community: CommunityData;
    updateAvailable: boolean;
    latestVersion: string | null;
    latestReleaseUrl: string | null;
};

export const meQuery = queryOptions({
    queryKey: ['me'],
    queryFn: () => apiCall<MeData>(apiClient.GET('/me')),
    staleTime: 30_000,
    retry: (failureCount, error) =>
        // 401/403 are steady states — don't retry them.
        !(
            error instanceof Error &&
            'status' in error &&
            [401, 403].includes((error as { status: number }).status)
        ) && failureCount < 2,
});

/**
 * Session bootstrap for the app shell — replaces `usePage().props`.
 * Authed routes call this after the route guard has already warmed the query.
 */
export function useMe() {
    return useQuery(meQuery);
}

export function useMeData(): MeData | undefined {
    return useQuery(meQuery).data;
}

export function useInvalidateMe() {
    const queryClient = useQueryClient();

    return () => queryClient.invalidateQueries({ queryKey: meQuery.queryKey });
}

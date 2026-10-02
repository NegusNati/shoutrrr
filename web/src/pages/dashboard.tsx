import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useSearch } from '@tanstack/react-router';
import { useEffect, useRef } from 'react';

import Composer from '@/components/compose/composer';
import { DashboardAura } from '@/components/dashboard/dashboard-aura';
import { RecentFeed } from '@/components/dashboard/recent-feed';
import { GettingStartedCard } from '@/components/onboarding/getting-started-card';
import { WelcomeModal } from '@/components/onboarding/welcome-modal';
import { RecentFeedSkeleton } from '@/components/skeletons/recent-feed-skeleton';
import {
    Empty,
    EmptyDescription,
    EmptyHeader,
    EmptyMedia,
    EmptyTitle,
} from '@/components/ui/empty';
import { Plug } from '@/components/ui/icons';
import { useMeData } from '@/features/me/me';
import { dashboardQuery } from '@/features/posts/posts';
import { useDocumentTitle } from '@/hooks/use-document-title';
import { parseDestinationParam } from '@/lib/compose/composer-state';
import { shouldShowDashboardNoAccountsNotice } from '@/lib/dashboard/accounts';
import { appUrl } from '@/lib/href';

function timeGreeting(): string {
    const hour = new Date().getHours();
    if (hour < 5) {
        return 'Working late';
    }
    if (hour < 12) {
        return 'Good morning';
    }
    if (hour < 18) {
        return 'Good afternoon';
    }

    return 'Good evening';
}

function NoAccountsNotice() {
    return (
        <Empty className="mb-7 min-h-72 bg-card/80 backdrop-blur-sm">
            <EmptyHeader>
                <EmptyMedia variant="icon">
                    <Plug />
                </EmptyMedia>
                <EmptyTitle>No accounts connected yet</EmptyTitle>
                <EmptyDescription>
                    An admin needs to connect a workspace account before you can
                    compose and publish posts here.
                </EmptyDescription>
            </EmptyHeader>
            <a
                href={appUrl('/accounts')}
                className="text-sm font-medium text-primary underline-offset-4 hover:underline"
            >
                View connected accounts
            </a>
        </Empty>
    );
}

export default function DashboardPage() {
    useDocumentTitle('Dashboard');
    const me = useMeData();
    const { data, isLoading } = useQuery(dashboardQuery);
    const queryClient = useQueryClient();

    // Autosave persists drafts via standalone fetches, so the recent-posts
    // feed would stay stale after a save. Refresh the query when the composer
    // reports a save, debounced so a burst of keystroke-driven saves coalesces
    // into one refetch.
    const reloadTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
    function refreshRecentPosts() {
        if (reloadTimer.current) {
            clearTimeout(reloadTimer.current);
        }
        reloadTimer.current = setTimeout(() => {
            void queryClient.invalidateQueries({
                queryKey: dashboardQuery.queryKey,
            });
        }, 600);
    }
    useEffect(
        () => () => {
            if (reloadTimer.current) {
                clearTimeout(reloadTimer.current);
            }
        },
        [],
    );

    // A calendar slot click opens the composer here with a pre-set schedule
    // time; a "compose for channel" action arrives with ?destination=….
    const { schedule_at, destination } = useSearch({
        from: '/_app/dashboard',
    });
    const initialScheduleAt = schedule_at ?? null;
    const initialDestination = parseDestinationParam(destination ?? null);

    const firstName = (me?.auth.user?.name ?? '').split(/\s+/)[0] || 'there';
    const showNoAccountsNotice = shouldShowDashboardNoAccountsNotice(
        me?.shell.accounts ?? [],
        me?.workspaces.current?.permissions ?? [],
    );

    const onboarding = data?.onboarding ?? null;
    const posts = data?.posts ?? [];

    return (
        <div className="relative isolate mx-auto w-full max-w-7xl px-4 pt-6 pb-16 sm:px-6">
            <DashboardAura />
            {onboarding && <WelcomeModal welcomed={onboarding.welcomed} />}
            <h1 className="text-[26px] leading-tight font-semibold tracking-tight">
                {timeGreeting()},{' '}
                {/* Brand-green gradient name. Stops are derived from
                    --primary but darkened for light mode (the raw token is
                    too light to read on a white background, and the aura
                    sits behind it) and brightened for dark mode. */}
                <span className="bg-gradient-to-br from-[color-mix(in_oklch,var(--primary)_70%,black)] to-[color-mix(in_oklch,var(--primary)_48%,black)] bg-clip-text text-transparent dark:from-primary dark:to-[color-mix(in_oklch,var(--primary)_65%,white)]">
                    {firstName}
                </span>
            </h1>
            <p className="mt-1.5 mb-7 text-[13.5px] tracking-tight text-muted-foreground">
                Plan, write, and schedule your posts in one place.
            </p>

            {onboarding && <GettingStartedCard onboarding={onboarding} />}

            {showNoAccountsNotice && <NoAccountsNotice />}

            {me && data && (
                <Composer
                    key={`${initialScheduleAt ?? ''}:${JSON.stringify(initialDestination)}`}
                    post={null}
                    accounts={me.shell.accounts}
                    sets={me.shell.sets}
                    limits={me.shell.limits}
                    initialScheduleAt={initialScheduleAt}
                    initialDestination={initialDestination}
                    initialSavedMentions={data.savedMentions}
                    autoFocusEditor
                    onSaved={refreshRecentPosts}
                />
            )}

            {isLoading ? <RecentFeedSkeleton /> : <RecentFeed posts={posts} />}
        </div>
    );
}

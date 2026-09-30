import { useQueryClient } from '@tanstack/react-query';
import { useEffect } from 'react';

import { ThemeToggle } from '@/components/common/theme-toggle';
import { Breadcrumbs } from '@/components/layout/breadcrumbs';
import { NotificationBell } from '@/components/notifications/notification-bell';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { meQuery } from '@/features/me/me';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

const CHROME_POLL_MS = 60_000;

/**
 * Polls the session bootstrap so header chrome (notification badge, sidebar
 * unread counts) stays fresh — the SPA equivalent of the old live-props poll.
 */
function useChromeRefresh(intervalMs: number) {
    const queryClient = useQueryClient();

    useEffect(() => {
        const id = setInterval(() => {
            void queryClient.invalidateQueries({
                queryKey: meQuery.queryKey,
            });
        }, intervalMs);

        return () => clearInterval(id);
    }, [intervalMs, queryClient]);
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    useChromeRefresh(CHROME_POLL_MS);

    return (
        <header className="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-2 border-b border-sidebar-border/50 bg-background/85 px-6 backdrop-blur-md transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <div className="ml-auto flex items-center gap-1.5">
                <NotificationBell />
                <ThemeToggle />
            </div>
        </header>
    );
}

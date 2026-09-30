import { createFileRoute, Outlet, redirect } from '@tanstack/react-router';

import { AppContent } from '@/components/layout/app-content';
import { AppShell } from '@/components/layout/app-shell';
import { AppSidebar } from '@/components/layout/app-sidebar';
import { AppSidebarHeader } from '@/components/layout/app-sidebar-header';
import { meQuery } from '@/features/me/me';
import { ApiError } from '@/lib/api';

/**
 * Pathless layout for authenticated pages. Mirrors the web `auth` middleware:
 * the session bootstrap must resolve before any page underneath renders.
 */
export const Route = createFileRoute('/_app')({
    beforeLoad: async ({ context }) => {
        try {
            await context.queryClient.ensureQueryData(meQuery);
        } catch (error) {
            if (
                error instanceof ApiError &&
                (error.status === 401 || error.status === 403)
            ) {
                throw redirect({ to: '/login' });
            }
            throw error;
        }
    },
    component: AppLayoutRoute,
});

function AppLayoutRoute() {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar" className="overflow-x-hidden">
                <AppSidebarHeader />
                <Outlet />
            </AppContent>
        </AppShell>
    );
}

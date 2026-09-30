import { createFileRoute } from '@tanstack/react-router';

import { dashboardQuery } from '@/features/posts/posts';
import DashboardPage from '@/pages/dashboard';

export const Route = createFileRoute('/_app/dashboard')({
    loader: ({ context }) =>
        context.queryClient.ensureQueryData(dashboardQuery),
    component: DashboardPage,
});

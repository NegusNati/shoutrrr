import { createFileRoute } from '@tanstack/react-router';

import { dashboardQuery } from '@/features/posts/posts';
import DashboardPage from '@/pages/dashboard';

export const Route = createFileRoute('/_app/dashboard')({
    validateSearch: (search: Record<string, unknown>) => {
        const params: { schedule_at?: string; destination?: string } = {};
        if (typeof search.schedule_at === 'string') {
            params.schedule_at = search.schedule_at;
        }
        if (typeof search.destination === 'string') {
            params.destination = search.destination;
        }
        return params;
    },
    loader: ({ context }) =>
        context.queryClient.ensureQueryData(dashboardQuery),
    component: DashboardPage,
});

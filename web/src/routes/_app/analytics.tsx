import { createFileRoute } from '@tanstack/react-router';

import AnalyticsPage from '@/pages/analytics';

export const Route = createFileRoute('/_app/analytics')({
    validateSearch: (search: Record<string, unknown>): { days: number } => ({
        days:
            typeof search.days === 'number' && search.days >= 1
                ? Math.min(365, Math.floor(search.days))
                : 90,
    }),
    component: AnalyticsRoute,
});

function AnalyticsRoute() {
    const { days } = Route.useSearch();

    return <AnalyticsPage days={days} />;
}

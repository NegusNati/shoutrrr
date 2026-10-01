import { createFileRoute } from '@tanstack/react-router';

import AnalyticsIndexPage, {
    type AnalyticsSearch,
} from '@/pages/analytics/index';

export const Route = createFileRoute('/_app/analytics/')({
    validateSearch: (search: Record<string, unknown>): AnalyticsSearch => ({
        days: typeof search.days === 'number' ? search.days : 90,
    }),
    component: AnalyticsIndexRoute,
});

function AnalyticsIndexRoute() {
    const search = Route.useSearch();

    return <AnalyticsIndexPage search={search} />;
}
